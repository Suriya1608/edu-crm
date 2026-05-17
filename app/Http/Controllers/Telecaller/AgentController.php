<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadMeeting;
use App\Models\WhatsAppMessage;
use App\Services\AuditLogService;
use App\Services\GoogleMeetService;
use App\Services\WhatsAppService;
use App\Services\ZoomService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    private const MODEL     = 'claude-sonnet-4-6';
    private const MAX_TURNS = 8;

    public function __construct(
        private readonly GoogleMeetService $googleMeet,
        private readonly ZoomService       $zoom,
    ) {}

    // ─── Entry point ──────────────────────────────────────────────────────────
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:600',
            'history' => 'nullable|array|max:12',
        ]);

        $apiKey = config('services.anthropic.api_key');
        if (!$apiKey) {
            return response()->json([
                'type'    => 'error',
                'icon'    => 'key_off',
                'message' => "API key not configured.\n\nAdd `ANTHROPIC_API_KEY=sk-ant-...` to your **.env** file.",
            ]);
        }

        $user    = Auth::user();
        $today   = now()->setTimezone('Asia/Kolkata')->format('l, d F Y');
        $nowTime = now()->setTimezone('Asia/Kolkata')->format('g:i A');

        $system = <<<SYSTEM
You are an intelligent CRM assistant for {$user->name}, a telecaller in an education admissions CRM.
Today: {$today}. Current time: {$nowTime} IST.

## What you can do
- Find leads and get their full summary (status, last call, last note, next follow-up)
- List new / uncontacted leads assigned to you
- Schedule Google Meet or Zoom meetings for leads
- Cancel scheduled meetings for leads
- Schedule or reschedule follow-up reminders
- Mark follow-ups as done
- Update lead status
- List follow-ups (today / tomorrow / upcoming / overdue)
- Send a WhatsApp message to a lead
- Get your performance stats for today / this week / this month

## Rules
1. **Always call find_lead first** when the user mentions a person's name — never assume the lead_id.
2. If find_lead returns multiple leads, ask the user which one they mean.
3. To cancel a meeting: call find_lead → list_meetings → cancel_meeting.
4. To reschedule a follow-up: call find_lead → reschedule_followup with new datetime.
5. When scheduling, always confirm the exact date & time back to the user.
6. If a meeting link is returned, always include it prominently in your reply.
7. Be concise, friendly, and professional.
8. Never invent lead data. Only use what tools return.
9. If something fails, explain why and suggest next steps.
SYSTEM;

        // Build history (filter to valid roles only)
        $history = collect($request->input('history', []))
            ->filter(fn($m) => isset($m['role'], $m['content']) && in_array($m['role'], ['user', 'assistant']))
            ->map(fn($m) => ['role' => $m['role'], 'content' => (string) $m['content']])
            ->values()
            ->toArray();

        $messages = array_merge(
            $history,
            [['role' => 'user', 'content' => $request->input('message')]]
        );

        return response()->json($this->runAgentLoop($apiKey, $system, $messages));
    }

    // ─── Agentic loop ─────────────────────────────────────────────────────────
    private function runAgentLoop(string $apiKey, string $system, array $messages): array
    {
        $tools = $this->toolDefinitions();
        $turns = 0;

        while ($turns < self::MAX_TURNS) {
            $turns++;

            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 1024,
                    'system'     => $system,
                    'tools'      => $tools,
                    'messages'   => $messages,
                ]);

            if (!$response->successful()) {
                $err = $response->json('error.message', 'Unknown error');
                $status = $response->status();
                return [
                    'type'    => 'error',
                    'icon'    => 'cloud_off',
                    'message' => "Claude API error ({$status}): {$err}",
                ];
            }

            $body       = $response->json();
            $stopReason = $body['stop_reason'] ?? 'end_turn';
            $content    = $body['content']     ?? [];

            // ── Final text response ───────────────────────────────────────────
            if ($stopReason === 'end_turn') {
                $text = collect($content)
                    ->where('type', 'text')
                    ->pluck('text')
                    ->join("\n\n");

                return [
                    'type'    => 'ai',
                    'icon'    => 'smart_toy',
                    'message' => $text ?: 'Done.',
                    // Return assistant message for history
                    'assistant_message' => ['role' => 'assistant', 'content' => $text ?: 'Done.'],
                ];
            }

            // ── Tool use: execute each tool then continue ─────────────────────
            if ($stopReason === 'tool_use') {
                // Add assistant's full content block to messages
                $normalizedContent = array_map(function ($block) {
                    if (($block['type'] ?? '') === 'tool_use' && empty($block['input'])) {
                        $block['input'] = new \stdClass();
                    }
                    return $block;
                }, $content);
                $messages[] = ['role' => 'assistant', 'content' => $normalizedContent];

                $toolResults = [];
                foreach ($content as $block) {
                    if (($block['type'] ?? '') !== 'tool_use') continue;

                    $result        = $this->executeTool($block['name'], $block['input'] ?? []);
                    $toolResults[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            // Unknown stop reason
            break;
        }

        return [
            'type'    => 'error',
            'icon'    => 'loop',
            'message' => 'The agent took too many steps. Please try a simpler request.',
        ];
    }

    // ─── Tool dispatcher ──────────────────────────────────────────────────────
    private function executeTool(string $name, array $input): array
    {
        return match ($name) {
            'find_lead'             => $this->toolFindLead($input),
            'get_lead_summary'      => $this->toolGetLeadSummary($input),
            'list_new_leads'        => $this->toolListNewLeads($input),
            'schedule_followup'     => $this->toolScheduleFollowup($input),
            'reschedule_followup'   => $this->toolRescheduleFollowup($input),
            'mark_followup_done'    => $this->toolMarkFollowupDone($input),
            'schedule_google_meet'  => $this->toolScheduleGoogleMeet($input),
            'schedule_zoom_meet'    => $this->toolScheduleZoomMeet($input),
            'update_lead_status'    => $this->toolUpdateStatus($input),
            'list_followups'        => $this->toolListFollowups($input),
            'list_meetings'         => $this->toolListMeetings($input),
            'cancel_meeting'        => $this->toolCancelMeeting($input),
            'get_my_stats'          => $this->toolGetMyStats($input),
            'send_whatsapp_message' => $this->toolSendWhatsApp($input),
            default                 => ['ok' => false, 'error' => "Unknown tool: {$name}"],
        };
    }

    // ─── Tool: find_lead ──────────────────────────────────────────────────────
    private function toolFindLead(array $input): array
    {
        $term = trim($input['name'] ?? '');

        $query = Lead::where('assigned_to', Auth::id());

        if (strlen($term) >= 2) {
            $query->where(fn($q) =>
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('lead_code', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
            );
        }

        $leads = $query->limit(5)->get(['id', 'name', 'phone', 'email', 'lead_code', 'status']);

        if ($leads->isEmpty()) {
            return ['ok' => false, 'error' => "No lead found matching '{$term}'. The user should check the name or lead code."];
        }

        return [
            'ok'    => true,
            'leads' => $leads->map(fn($l) => [
                'id'        => $l->id,
                'name'      => $l->name,
                'phone'     => $l->phone,
                'email'     => $l->email,
                'lead_code' => $l->lead_code,
                'status'    => $l->status,
            ])->toArray(),
        ];
    }

    // ─── Tool: schedule_followup ──────────────────────────────────────────────
    private function toolScheduleFollowup(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        try {
            $at = Carbon::parse($input['datetime'])->setTimezone('Asia/Kolkata');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Invalid datetime. Use ISO format: 2026-05-05T15:30:00'];
        }

        if ($at->isPast()) {
            return ['ok' => false, 'error' => 'The scheduled datetime is in the past. Please provide a future time.'];
        }

        Followup::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'remarks'       => $input['notes'] ?? 'Scheduled via AI assistant',
            'next_followup' => $at->toDateString(),
            'followup_time' => $at->format('H:i'),
        ]);

        $lead->update(['status' => 'follow_up']);

        AuditLogService::log('lead.followup_scheduled', 'Lead', $lead->id, [],
            ['datetime' => $at->toDateTimeString(), 'source' => 'ai_agent']
        );

        return [
            'ok'           => true,
            'lead_name'    => $lead->name,
            'scheduled_at' => $at->format('D, d M Y \a\t g:i A'),
        ];
    }

    // ─── Tool: schedule_google_meet ───────────────────────────────────────────
    private function toolScheduleGoogleMeet(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        try {
            $startTime = new \DateTime($input['datetime'], new \DateTimeZone('Asia/Kolkata'));
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Invalid datetime format.'];
        }

        if ($startTime < new \DateTime()) {
            return ['ok' => false, 'error' => 'Meeting time is in the past.'];
        }

        $duration = max(15, (int) ($input['duration_minutes'] ?? 60));
        $title    = trim($input['title'] ?? '') ?: "Meeting with {$lead->name}";

        $result = $this->googleMeet->createMeet(
            title:           $title,
            startTime:       $startTime,
            durationMinutes: $duration,
            attendeeEmail:   $lead->email,
            attendeeName:    $lead->name,
            notes:           $input['notes'] ?? null,
        );

        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Google Meet creation failed.'];
        }

        LeadMeeting::create([
            'lead_id'         => $lead->id,
            'created_by'      => Auth::id(),
            'title'           => $title,
            'meeting_link'    => $result['link'],
            'google_event_id' => $result['event_id'] ?? null,
            'meeting_time'    => $startTime,
            'duration'        => $duration,
            'notes'           => $input['notes'] ?? null,
            'status'          => 'scheduled',
        ]);

        $timeStr = (new Carbon($startTime))->format('D, d M Y \a\t g:i A');
        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'meeting',
            'description'   => "Google Meet scheduled for {$timeStr} via AI assistant.",
            'activity_time' => now(),
        ]);

        return [
            'ok'           => true,
            'lead_name'    => $lead->name,
            'meet_link'    => $result['link'],
            'scheduled_at' => $timeStr,
            'duration_min' => $duration,
            'email_sent'   => $result['email_sent'] ?? false,
        ];
    }

    // ─── Tool: schedule_zoom_meet ─────────────────────────────────────────────
    private function toolScheduleZoomMeet(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        if (!$this->zoom->isConfigured()) {
            return ['ok' => false, 'error' => 'Zoom is not configured on this CRM. Use Google Meet instead.'];
        }

        try {
            $startTime = new \DateTime($input['datetime'], new \DateTimeZone('Asia/Kolkata'));
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Invalid datetime format.'];
        }

        $duration = max(15, (int) ($input['duration_minutes'] ?? 60));
        $title    = trim($input['title'] ?? '') ?: "Zoom with {$lead->name}";

        $result = $this->zoom->createMeeting(
            title:           $title,
            startTime:       $startTime,
            durationMinutes: $duration,
            attendeeEmail:   $lead->email,
            attendeeName:    $lead->name,
            notes:           $input['notes'] ?? null,
        );

        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Zoom meeting creation failed.'];
        }

        LeadMeeting::create([
            'lead_id'         => $lead->id,
            'created_by'      => Auth::id(),
            'title'           => $title,
            'meeting_link'    => $result['link'],
            'zoom_meeting_id' => $result['meeting_id'] ?? null,
            'meeting_time'    => $startTime,
            'duration'        => $duration,
            'notes'           => $input['notes'] ?? null,
            'status'          => 'scheduled',
            'meeting_type'    => 'zoom',
        ]);

        $timeStr = (new Carbon($startTime))->format('D, d M Y \a\t g:i A');
        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'meeting',
            'description'   => "Zoom meeting scheduled for {$timeStr} via AI assistant.",
            'activity_time' => now(),
        ]);

        return [
            'ok'           => true,
            'lead_name'    => $lead->name,
            'zoom_link'    => $result['link'],
            'scheduled_at' => $timeStr,
            'duration_min' => $duration,
        ];
    }

    // ─── Tool: update_lead_status ─────────────────────────────────────────────
    private function toolUpdateStatus(array $input): array
    {
        $valid  = ['new', 'assigned', 'contacted', 'interested', 'follow_up', 'not_interested', 'converted'];
        $status = $input['status'] ?? '';

        if (!in_array($status, $valid)) {
            return ['ok' => false, 'error' => "Invalid status '{$status}'. Valid values: " . implode(', ', $valid)];
        }

        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        $old = $lead->status;
        $lead->update(['status' => $status]);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'status_change',
            'description'   => 'Status changed to ' . ucfirst(str_replace('_', ' ', $status)) . ' via AI assistant',
            'activity_time' => now(),
        ]);

        AuditLogService::log('lead.status_changed', 'Lead', $lead->id,
            ['status' => $old],
            ['status' => $status, 'source' => 'ai_agent']
        );

        return [
            'ok'         => true,
            'lead_name'  => $lead->name,
            'old_status' => $old,
            'new_status' => $status,
        ];
    }

    // ─── Tool: list_followups ─────────────────────────────────────────────────
    private function toolListFollowups(array $input): array
    {
        $scope = in_array($input['scope'] ?? '', ['today', 'tomorrow', 'upcoming', 'overdue'])
            ? $input['scope']
            : 'today';

        $query = Followup::with('lead:id,name,phone,lead_code')
            ->where('user_id', Auth::id())
            ->whereNull('completed_at');

        match ($scope) {
            'overdue'  => $query->whereDate('next_followup', '<', today()),
            'tomorrow' => $query->whereDate('next_followup', today()->addDay()),
            'upcoming' => $query->whereDate('next_followup', '>', today()),
            default    => $query->whereDate('next_followup', today()),
        };

        $items = $query->orderBy('next_followup')->orderBy('followup_time')->limit(10)->get();

        return [
            'ok'        => true,
            'scope'     => $scope,
            'count'     => $items->count(),
            'followups' => $items->map(fn($f) => [
                'name'  => $f->lead->name ?? 'Unknown',
                'code'  => $f->lead->lead_code ?? '-',
                'phone' => $f->lead->phone ?? '-',
                'date'  => Carbon::parse($f->next_followup)->format('d M Y'),
                'time'  => Carbon::parse($f->followup_time)->format('g:i A'),
            ])->toArray(),
        ];
    }

    // ─── Tool: list_meetings ──────────────────────────────────────────────────
    private function toolListMeetings(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        $meetings = LeadMeeting::where('lead_id', $lead->id)
            ->where('status', 'scheduled')
            ->orderBy('meeting_time')
            ->limit(5)
            ->get();

        if ($meetings->isEmpty()) {
            return ['ok' => true, 'lead_name' => $lead->name, 'meetings' => [], 'message' => 'No scheduled meetings found for this lead.'];
        }

        return [
            'ok'       => true,
            'lead_name' => $lead->name,
            'meetings' => $meetings->map(fn($m) => [
                'id'           => $m->id,
                'title'        => $m->title,
                'type'         => $m->meeting_type ?? 'google_meet',
                'meeting_link' => $m->meeting_link,
                'scheduled_at' => Carbon::parse($m->meeting_time)->format('D, d M Y \a\t g:i A'),
                'duration_min' => $m->duration,
            ])->toArray(),
        ];
    }

    // ─── Tool: get_lead_summary ───────────────────────────────────────────────
    private function toolGetLeadSummary(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->with(['enrolledCourse:id,name'])
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        $lastCall = CallLog::where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->first(['created_at', 'duration', 'outcome', 'direction']);

        $lastNote = LeadActivity::where('lead_id', $lead->id)
            ->where('type', 'note')
            ->orderByDesc('activity_time')
            ->value('description');

        $nextFollowup = Followup::where('lead_id', $lead->id)
            ->whereNull('completed_at')
            ->orderBy('next_followup')
            ->orderBy('followup_time')
            ->first(['next_followup', 'followup_time', 'remarks']);

        return [
            'ok'           => true,
            'name'         => $lead->name,
            'lead_code'    => $lead->lead_code,
            'phone'        => $lead->phone,
            'email'        => $lead->email,
            'status'       => $lead->status,
            'course'       => $lead->enrolledCourse?->name ?? 'Not specified',
            'days_aged'    => (int) now()->diffInDays($lead->created_at),
            'last_call'    => $lastCall ? [
                'date'      => Carbon::parse($lastCall->created_at)->format('d M Y, g:i A'),
                'duration'  => $lastCall->duration ? gmdate('i:s', (int) $lastCall->duration) : '—',
                'outcome'   => $lastCall->outcome ?? 'No outcome logged',
                'direction' => $lastCall->direction ?? 'outbound',
            ] : null,
            'last_note'    => $lastNote ?? null,
            'next_followup' => $nextFollowup ? [
                'date'    => Carbon::parse($nextFollowup->next_followup)->format('d M Y'),
                'time'    => Carbon::parse($nextFollowup->followup_time)->format('g:i A'),
                'remarks' => $nextFollowup->remarks,
            ] : null,
        ];
    }

    // ─── Tool: list_new_leads ─────────────────────────────────────────────────
    private function toolListNewLeads(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 10), 15);

        $leads = Lead::where('assigned_to', Auth::id())
            ->where('status', 'new')
            ->with(['enrolledCourse:id,name'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'phone', 'lead_code', 'course_id', 'created_at']);

        return [
            'ok'     => true,
            'count'  => $leads->count(),
            'leads'  => $leads->map(fn($l) => [
                'id'        => $l->id,
                'name'      => $l->name,
                'phone'     => $l->phone,
                'lead_code' => $l->lead_code,
                'course'    => $l->enrolledCourse?->name ?? 'Not specified',
                'assigned'  => Carbon::parse($l->created_at)->format('d M Y'),
            ])->toArray(),
        ];
    }

    // ─── Tool: get_my_stats ───────────────────────────────────────────────────
    private function toolGetMyStats(array $input): array
    {
        $scope  = in_array($input['scope'] ?? '', ['today', 'week', 'month']) ? $input['scope'] : 'today';
        $userId = Auth::id();

        [$start, $end, $label] = match ($scope) {
            'week'  => [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY), 'This Week'],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), 'This Month'],
            default => [now()->startOfDay(), now()->endOfDay(), 'Today'],
        };

        $callsBase = CallLog::where('user_id', $userId)->whereBetween('created_at', [$start, $end]);

        $totalCalls  = (clone $callsBase)->count();
        $talkSeconds = (int) (clone $callsBase)->sum('duration');

        $outcomes = (clone $callsBase)
            ->whereNotNull('outcome')
            ->selectRaw('outcome, COUNT(*) as cnt')
            ->groupBy('outcome')
            ->pluck('cnt', 'outcome')
            ->toArray();

        $followupsDone = Followup::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->count();

        $overdueFollowups = Followup::where('user_id', $userId)
            ->whereNull('completed_at')
            ->whereDate('next_followup', '<', today())
            ->count();

        $converted = Lead::where('assigned_to', $userId)
            ->where('status', 'converted')
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        return [
            'ok'                => true,
            'scope'             => $label,
            'total_calls'       => $totalCalls,
            'talk_time'         => gmdate('H:i:s', $talkSeconds),
            'outcomes'          => $outcomes,
            'followups_done'    => $followupsDone,
            'overdue_followups' => $overdueFollowups,
            'converted_leads'   => $converted,
        ];
    }

    // ─── Tool: mark_followup_done ─────────────────────────────────────────────
    private function toolMarkFollowupDone(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        $followup = Followup::where('lead_id', $lead->id)
            ->where('user_id', Auth::id())
            ->whereNull('completed_at')
            ->orderBy('next_followup')
            ->first();

        if (!$followup) {
            return ['ok' => false, 'error' => "No pending follow-up found for {$lead->name}."];
        }

        $followup->update(['completed_at' => now()]);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'followup',
            'description'   => 'Follow-up marked as completed via AI assistant.',
            'activity_time' => now(),
        ]);

        AuditLogService::log('lead.followup_completed', 'Followup', $followup->id, ['completed_at' => null], ['completed_at' => now()->toDateTimeString(), 'source' => 'ai_agent']);

        return [
            'ok'          => true,
            'lead_name'   => $lead->name,
            'was_due'     => Carbon::parse($followup->next_followup)->format('d M Y') . ' at ' . Carbon::parse($followup->followup_time)->format('g:i A'),
            'remarks'     => $followup->remarks,
        ];
    }

    // ─── Tool: reschedule_followup ────────────────────────────────────────────
    private function toolRescheduleFollowup(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        try {
            $at = Carbon::parse($input['datetime'])->setTimezone('Asia/Kolkata');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Invalid datetime. Use ISO format: 2026-05-05T15:30:00'];
        }

        if ($at->isPast()) {
            return ['ok' => false, 'error' => 'The new datetime is in the past. Please provide a future time.'];
        }

        $followup = Followup::where('lead_id', $lead->id)
            ->where('user_id', Auth::id())
            ->whereNull('completed_at')
            ->orderBy('next_followup')
            ->first();

        if (!$followup) {
            // No existing follow-up — create a new one
            Followup::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'remarks'       => $input['notes'] ?? 'Rescheduled via AI assistant',
                'next_followup' => $at->toDateString(),
                'followup_time' => $at->format('H:i'),
            ]);
            $lead->update(['status' => 'follow_up']);
            return ['ok' => true, 'lead_name' => $lead->name, 'action' => 'created', 'scheduled_at' => $at->format('D, d M Y \a\t g:i A')];
        }

        $old = Carbon::parse($followup->next_followup)->format('d M Y');
        $followup->update([
            'next_followup'        => $at->toDateString(),
            'followup_time'        => $at->format('H:i'),
            'reminder_notified_at' => null,
            'remarks'              => $input['notes'] ?? $followup->remarks,
        ]);

        AuditLogService::log('lead.followup_rescheduled', 'Followup', $followup->id, ['next_followup' => $old], ['next_followup' => $at->toDateString(), 'source' => 'ai_agent']);

        return [
            'ok'           => true,
            'lead_name'    => $lead->name,
            'action'       => 'rescheduled',
            'old_date'     => $old,
            'scheduled_at' => $at->format('D, d M Y \a\t g:i A'),
        ];
    }

    // ─── Tool: send_whatsapp_message ──────────────────────────────────────────
    private function toolSendWhatsApp(array $input): array
    {
        $lead = Lead::where('id', $input['lead_id'] ?? 0)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        if (!$lead->phone) {
            return ['ok' => false, 'error' => "No phone number on record for {$lead->name}."];
        }

        $message = trim($input['message'] ?? '');
        if ($message === '') {
            return ['ok' => false, 'error' => 'Message cannot be empty.'];
        }

        /** @var WhatsAppService $wa */
        $wa = app(WhatsAppService::class);

        if (!$wa->isConfigured()) {
            return ['ok' => false, 'error' => 'WhatsApp is not configured on this CRM. Contact your admin.'];
        }

        $to = preg_replace('/\D/', '', (string) $lead->phone);
        if (!str_starts_with($to, '91')) {
            $to = '91' . ltrim($to, '0');
        }

        $inbound24h = WhatsAppMessage::where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        $result = $wa->send($to, $message, $inbound24h, (string) $lead->name);

        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'WhatsApp send failed.'];
        }

        WhatsAppMessage::create([
            'lead_id'             => $lead->id,
            'from_number'         => config('services.meta_whatsapp.phone_number_id', ''),
            'message_body'        => $inbound24h ? $message : 'Template sent (no active 24h window)',
            'direction'           => 'outbound',
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'provider'            => $result['provider'] ?? 'meta',
            'sent_at'             => now(),
        ]);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'whatsapp',
            'description'   => 'WhatsApp sent via AI assistant: ' . Str::limit($message, 80),
            'activity_time' => now(),
        ]);

        return [
            'ok'        => true,
            'lead_name' => $lead->name,
            'phone'     => $lead->phone,
            'sent'      => $inbound24h ? $message : 'WhatsApp template (no active session — lead must message first to receive free-form text)',
        ];
    }

    // ─── Tool: cancel_meeting ─────────────────────────────────────────────────
    private function toolCancelMeeting(array $input): array
    {
        $meeting = LeadMeeting::where('id', $input['meeting_id'] ?? 0)
            ->where('created_by', Auth::id())
            ->where('status', 'scheduled')
            ->first();

        if (!$meeting) {
            return ['ok' => false, 'error' => 'Meeting not found, already cancelled, or not created by you.'];
        }

        $lead = Lead::where('id', $meeting->lead_id)
            ->where('assigned_to', Auth::id())
            ->first();

        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found or not assigned to you.'];
        }

        $meeting->update(['status' => 'cancelled']);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'meeting',
            'description'   => "Meeting \"{$meeting->title}\" cancelled via AI assistant.",
            'activity_time' => now(),
        ]);

        AuditLogService::log('lead.meeting_cancelled', 'LeadMeeting', $meeting->id, ['status' => 'scheduled'], ['status' => 'cancelled', 'source' => 'ai_agent']);

        return [
            'ok'           => true,
            'lead_name'    => $lead->name,
            'meeting_title' => $meeting->title,
            'was_scheduled' => Carbon::parse($meeting->meeting_time)->format('D, d M Y \a\t g:i A'),
        ];
    }

    // ─── Tool definitions for Claude API ─────────────────────────────────────
    private function toolDefinitions(): array
    {
        return [
            [
                'name'         => 'find_lead',
                'description'  => 'Search for a lead assigned to this telecaller by name, lead code, or phone. Call this first whenever a person is mentioned.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name, lead code, or phone number to search for'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name'         => 'schedule_followup',
                'description'  => 'Schedule a follow-up reminder for a lead at a specific date and time. Updates lead status to follow_up.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lead_id'  => ['type' => 'integer', 'description' => 'Lead ID returned by find_lead'],
                        'datetime' => ['type' => 'string',  'description' => 'ISO 8601 datetime e.g. 2026-05-05T15:30:00'],
                        'notes'    => ['type' => 'string',  'description' => 'Reason or note for the follow-up (optional)'],
                    ],
                    'required' => ['lead_id', 'datetime'],
                ],
            ],
            [
                'name'         => 'schedule_google_meet',
                'description'  => 'Create a Google Meet session for a lead. Sends an email invite and WhatsApp notification. Returns the meeting link.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lead_id'          => ['type' => 'integer', 'description' => 'Lead ID returned by find_lead'],
                        'datetime'         => ['type' => 'string',  'description' => 'ISO 8601 datetime for the meeting'],
                        'duration_minutes' => ['type' => 'integer', 'description' => 'Duration in minutes, default 60'],
                        'title'            => ['type' => 'string',  'description' => 'Meeting title (optional)'],
                        'notes'            => ['type' => 'string',  'description' => 'Meeting agenda or notes (optional)'],
                    ],
                    'required' => ['lead_id', 'datetime'],
                ],
            ],
            [
                'name'         => 'schedule_zoom_meet',
                'description'  => 'Create a Zoom meeting for a lead. Returns the Zoom join link.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lead_id'          => ['type' => 'integer', 'description' => 'Lead ID returned by find_lead'],
                        'datetime'         => ['type' => 'string',  'description' => 'ISO 8601 datetime for the meeting'],
                        'duration_minutes' => ['type' => 'integer', 'description' => 'Duration in minutes, default 60'],
                        'title'            => ['type' => 'string',  'description' => 'Meeting title (optional)'],
                        'notes'            => ['type' => 'string',  'description' => 'Meeting notes (optional)'],
                    ],
                    'required' => ['lead_id', 'datetime'],
                ],
            ],
            [
                'name'         => 'update_lead_status',
                'description'  => 'Update the status of a lead. Valid statuses: new, assigned, contacted, interested, follow_up, not_interested, converted.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lead_id' => ['type' => 'integer', 'description' => 'Lead ID returned by find_lead'],
                        'status'  => ['type' => 'string',  'description' => 'new|assigned|contacted|interested|follow_up|not_interested|converted'],
                    ],
                    'required' => ['lead_id', 'status'],
                ],
            ],
            [
                'name'         => 'list_followups',
                'description'  => 'List the telecaller\'s follow-ups filtered by time scope.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'scope' => ['type' => 'string', 'description' => 'today | tomorrow | upcoming | overdue (default: today)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'         => 'list_meetings',
                'description'  => 'List all scheduled (upcoming) meetings for a lead. Use this before cancel_meeting to find the meeting ID.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lead_id' => ['type' => 'integer', 'description' => 'Lead ID returned by find_lead'],
                    ],
                    'required' => ['lead_id'],
                ],
            ],
            [
                'name'         => 'cancel_meeting',
                'description'  => 'Cancel a scheduled meeting by its ID. Call list_meetings first to get the meeting ID.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'meeting_id' => ['type' => 'integer', 'description' => 'Meeting ID from list_meetings'],
                    ],
                    'required' => ['meeting_id'],
                ],
            ],
        ];
    }
}
