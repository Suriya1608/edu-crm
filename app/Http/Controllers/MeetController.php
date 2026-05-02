<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadMeeting;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Services\GoogleMeetService;
use App\Services\WhatsAppService;
use App\Services\ZoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MeetController extends Controller
{
    public function __construct(
        private readonly GoogleMeetService $meetService,
        private readonly WhatsAppService   $whatsApp,
        private readonly ZoomService       $zoom,
    ) {}

    // ── Start Meet (instant — begins in 5 minutes) ─────────────────────────────

    public function startMeet(string $encryptedId): JsonResponse
    {
        $lead = $this->resolveAssignedLead($encryptedId);
        if ($lead instanceof JsonResponse) return $lead;

        // Guard: reuse active meeting created in last 30 minutes
        $existing = LeadMeeting::where('lead_id', $lead->id)
            ->where('status', 'scheduled')
            ->where('meeting_time', '>=', now()->subMinutes(30))
            ->first();

        if ($existing) {
            return response()->json([
                'ok'      => true,
                'meeting' => $this->formatMeeting($existing),
                'notice'  => 'An active meeting already exists.',
            ]);
        }

        $startTime = now()->addMinutes(5);

        $result = $this->meetService->createMeet(
            title:           "Meet with {$lead->name}",
            startTime:       $startTime,
            durationMinutes: 60,
            attendeeEmail:   $lead->email,
            attendeeName:    $lead->name,
            notes:           "Google Meet session initiated from Insight Tech CRM.",
        );

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 502);
        }

        $waSent = $this->sendWhatsApp($lead, $result['link'], $startTime, 60);

        $meeting = LeadMeeting::create([
            'lead_id'         => $lead->id,
            'created_by'      => Auth::id(),
            'title'           => "Meet with {$lead->name}",
            'meeting_link'    => $result['link'],
            'google_event_id' => $result['event_id'],
            'meeting_time'    => $startTime,
            'duration'        => 60,
            'status'          => 'scheduled',
            'whatsapp_sent'   => $waSent,
        ]);

        $channels = $this->channelsSummary($result['email_sent'], $waSent, $lead->email);
        $this->logActivity($lead, 'meeting', "Instant Meet started. Link: {$result['link']}. Notified via: {$channels}.");

        return response()->json([
            'ok'         => true,
            'meeting'    => $this->formatMeeting($meeting->fresh()),
            'email_sent' => $result['email_sent'],
            'wa_sent'    => $waSent,
        ]);
    }

    // ── Schedule Meet (custom time) ─────────────────────────────────────────────

    public function scheduleMeet(Request $request, string $encryptedId): JsonResponse
    {
        $request->validate([
            'meeting_time' => 'required|date|after:now',
            'duration'     => 'required|integer|min:15|max:480',
            'title'        => 'nullable|string|max:200',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $lead = $this->resolveAssignedLead($encryptedId);
        if ($lead instanceof JsonResponse) return $lead;

        $startTime = new \DateTime($request->meeting_time);
        $title     = trim($request->title) ?: "Meeting with {$lead->name}";

        $result = $this->meetService->createMeet(
            title:           $title,
            startTime:       $startTime,
            durationMinutes: (int) $request->duration,
            attendeeEmail:   $lead->email,
            attendeeName:    $lead->name,
            notes:           $request->notes,
        );

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 502);
        }

        // Always send WhatsApp on schedule
        $waSent = $this->sendWhatsApp($lead, $result['link'], $startTime, (int) $request->duration, $request->notes);

        $meeting = LeadMeeting::create([
            'lead_id'         => $lead->id,
            'created_by'      => Auth::id(),
            'title'           => $title,
            'meeting_link'    => $result['link'],
            'google_event_id' => $result['event_id'],
            'meeting_time'    => $startTime,
            'duration'        => (int) $request->duration,
            'notes'           => $request->notes,
            'status'          => 'scheduled',
            'whatsapp_sent'   => $waSent,
        ]);

        $timeStr  = $startTime->format('d M Y, h:i A');
        $channels = $this->channelsSummary($result['email_sent'], $waSent, $lead->email);
        $this->logActivity(
            $lead, 'meeting',
            "Meeting scheduled: {$title} on {$timeStr} ({$request->duration} min). Notified via: {$channels}."
        );

        return response()->json([
            'ok'         => true,
            'meeting'    => $this->formatMeeting($meeting->fresh()),
            'email_sent' => $result['email_sent'],
            'wa_sent'    => $waSent,
        ]);
    }

    // ── Start Zoom (instant — begins in 5 minutes) ────────────────────────────

    public function startZoomMeet(string $encryptedId): JsonResponse
    {
        $lead = $this->resolveAssignedLead($encryptedId);
        if ($lead instanceof JsonResponse) return $lead;

        if (!$this->zoom->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'Zoom is not configured. Ask your admin to set up Zoom credentials.'], 503);
        }

        $startTime = now()->addMinutes(5);

        $result = $this->zoom->createMeeting(
            title:           "Zoom with {$lead->name}",
            startTime:       $startTime,
            durationMinutes: 60,
            attendeeEmail:   $lead->email,
            attendeeName:    $lead->name,
            notes:           "Zoom meeting initiated from Insight Tech CRM.",
        );

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 502);
        }

        $waSent = $this->sendWhatsAppForZoom($lead, $result['link'], $startTime, 60);

        $meeting = LeadMeeting::create([
            'lead_id'        => $lead->id,
            'created_by'     => Auth::id(),
            'title'          => "Zoom with {$lead->name}",
            'meeting_link'   => $result['link'],
            'zoom_meeting_id'=> $result['meeting_id'],
            'meeting_time'   => $startTime,
            'duration'       => 60,
            'status'         => 'scheduled',
            'meeting_type'   => 'zoom',
            'whatsapp_sent'  => $waSent,
        ]);

        $this->logActivity($lead, 'meeting', "Instant Zoom started. Link: {$result['link']}. Notified via: " . ($waSent ? 'WhatsApp' : 'none') . '.');

        return response()->json([
            'ok'      => true,
            'meeting' => $this->formatMeeting($meeting->fresh()),
            'wa_sent' => $waSent,
        ]);
    }

    // ── Schedule Zoom (custom time) ─────────────────────────────────────────────

    public function scheduleZoomMeet(Request $request, string $encryptedId): JsonResponse
    {
        $request->validate([
            'meeting_time' => 'required|date|after:now',
            'duration'     => 'required|integer|min:15|max:480',
            'title'        => 'nullable|string|max:200',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $lead = $this->resolveAssignedLead($encryptedId);
        if ($lead instanceof JsonResponse) return $lead;

        if (!$this->zoom->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'Zoom is not configured. Ask your admin to set up Zoom credentials.'], 503);
        }

        $startTime = new \DateTime($request->meeting_time);
        $title     = trim($request->title) ?: "Zoom with {$lead->name}";

        $result = $this->zoom->createMeeting(
            title:           $title,
            startTime:       $startTime,
            durationMinutes: (int) $request->duration,
            attendeeEmail:   $lead->email,
            attendeeName:    $lead->name,
            notes:           $request->notes,
        );

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 502);
        }

        $waSent = $this->sendWhatsAppForZoom($lead, $result['link'], $startTime, (int) $request->duration, $request->notes);

        $meeting = LeadMeeting::create([
            'lead_id'        => $lead->id,
            'created_by'     => Auth::id(),
            'title'          => $title,
            'meeting_link'   => $result['link'],
            'zoom_meeting_id'=> $result['meeting_id'],
            'meeting_time'   => $startTime,
            'duration'       => (int) $request->duration,
            'notes'          => $request->notes,
            'status'         => 'scheduled',
            'meeting_type'   => 'zoom',
            'whatsapp_sent'  => $waSent,
        ]);

        $timeStr = $startTime->format('d M Y, h:i A');
        $this->logActivity(
            $lead, 'meeting',
            "Zoom scheduled: {$title} on {$timeStr} ({$request->duration} min). Notified via: " . ($waSent ? 'WhatsApp' : 'none') . '.'
        );

        return response()->json([
            'ok'      => true,
            'meeting' => $this->formatMeeting($meeting->fresh()),
            'wa_sent' => $waSent,
        ]);
    }

    // ── Update meeting status ──────────────────────────────────────────────────

    public function updateStatus(Request $request, int $meetingId): JsonResponse
    {
        $request->validate(['status' => 'required|in:scheduled,completed,missed']);

        $meeting = LeadMeeting::findOrFail($meetingId);
        $lead    = Lead::where('id', $meeting->lead_id)
            ->where('assigned_to', Auth::id())
            ->firstOrFail();

        $meeting->update(['status' => $request->status]);
        $this->logActivity($lead, 'meeting', "Meeting \"{$meeting->title}\" marked as {$request->status}.");

        return response()->json(['ok' => true, 'status' => $request->status]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function resolveAssignedLead(string $encryptedId): Lead|JsonResponse
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception) {
            return response()->json(['ok' => false, 'error' => 'Invalid lead reference.'], 400);
        }

        $lead = Lead::where('id', $id)->where('assigned_to', Auth::id())->first();
        if (!$lead) {
            return response()->json(['ok' => false, 'error' => 'Lead not found or not assigned to you.'], 403);
        }

        return $lead;
    }

    private function logActivity(Lead $lead, string $type, string $description): void
    {
        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => $type,
            'description'   => $description,
            'activity_time' => now(),
        ]);
    }

    private function sendWhatsApp(Lead $lead, string $link, \DateTimeInterface $time, int $duration, ?string $notes = null): bool
    {
        if (!$this->whatsApp->isConfigured()) {
            return false;
        }

        $timeStr = \DateTime::createFromFormat('U', (string) $time->getTimestamp())
            ->setTimezone(new \DateTimeZone(config('app.timezone', 'Asia/Kolkata')))
            ->format('d M Y, h:i A');

        $message = "Hi {$lead->name},\n\n"
            . "You have a Google Meet session scheduled.\n\n"
            . "📅 Date & Time : {$timeStr}\n"
            . "⏱ Duration     : {$duration} minutes\n"
            . "🔗 Join Link   : {$link}";

        if ($notes) {
            $message .= "\n📝 Note: {$notes}";
        }

        $message .= "\n\nPlease click the link at the scheduled time to join.";

        // Meta only allows free-form text within the 24h inbound window.
        // If there's no active session, skip sending — the template can't carry the meet link.
        $inbound24h = Schema::hasTable('whatsapp_messages') && WhatsAppMessage::where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if (!$inbound24h) {
            return false;
        }

        $result = $this->whatsApp->send($lead->phone, $message, true, $lead->name);

        if (!($result['ok'] ?? false)) {
            return false;
        }

        // Save to whatsapp_messages so it appears in the lead's chat history
        $body = $message;

        $phoneNumberId = (string) Setting::get('meta_whatsapp_phone_number_id', '');

        $row = [
            'lead_id'             => $lead->id,
            'from_number'         => $phoneNumberId,
            'message_body'        => $body,
            'direction'           => 'outbound',
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'provider'            => 'meta',
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $lead->phone, 'source' => 'meet_schedule'],
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $body;
        }

        WhatsAppMessage::create($row);

        return true;
    }

    private function channelsSummary(bool $emailSent, bool $waSent, ?string $email): string
    {
        $parts = [];
        if ($emailSent && $email) {
            $parts[] = "Email ({$email})";
        }
        if ($waSent) {
            $parts[] = 'WhatsApp';
        }
        return $parts ? implode(', ', $parts) : 'none';
    }

    private function sendWhatsAppForZoom(Lead $lead, string $link, \DateTimeInterface $time, int $duration, ?string $notes = null): bool
    {
        if (!$this->whatsApp->isConfigured()) {
            return false;
        }

        $inbound24h = Schema::hasTable('whatsapp_messages') && WhatsAppMessage::where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if (!$inbound24h) {
            return false;
        }

        $timeStr = \DateTime::createFromFormat('U', (string) $time->getTimestamp())
            ->setTimezone(new \DateTimeZone(config('app.timezone', 'Asia/Kolkata')))
            ->format('d M Y, h:i A');

        $message = "Hi {$lead->name},\n\n"
            . "You have a Zoom meeting scheduled.\n\n"
            . "📅 Date & Time : {$timeStr}\n"
            . "⏱ Duration     : {$duration} minutes\n"
            . "🔗 Join Link   : {$link}";

        if ($notes) {
            $message .= "\n📝 Note: {$notes}";
        }

        $message .= "\n\nPlease click the link at the scheduled time to join.";

        $result = $this->whatsApp->send($lead->phone, $message, true, $lead->name);

        if (!($result['ok'] ?? false)) {
            return false;
        }

        $phoneNumberId = (string) Setting::get('meta_whatsapp_phone_number_id', '');

        $row = [
            'lead_id'             => $lead->id,
            'from_number'         => $phoneNumberId,
            'message_body'        => $message,
            'direction'           => 'outbound',
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'provider'            => 'meta',
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $lead->phone, 'source' => 'zoom_schedule'],
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $message;
        }

        WhatsAppMessage::create($row);

        return true;
    }

    private function formatMeeting(LeadMeeting $m): array
    {
        return [
            'id'               => $m->id,
            'title'            => $m->title,
            'meeting_link'     => $m->meeting_link,
            'meeting_time'     => $m->meeting_time?->format('d M Y, h:i A'),
            'meeting_time_iso' => $m->meeting_time?->toIso8601String(),
            'duration'         => $m->duration,
            'notes'            => $m->notes,
            'status'           => $m->status,
            'meeting_type'     => $m->meeting_type ?? 'google',
            'whatsapp_sent'    => $m->whatsapp_sent,
            'created_by'       => $m->creator?->name ?? '—',
            'created_at'       => $m->created_at?->format('d M Y'),
        ];
    }
}
