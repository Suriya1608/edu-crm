<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\CallService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ExotelController extends Controller
{
    public function __construct(protected CallService $callService) {}

    public function call(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
            'phone'   => 'required|string|max:20',
        ]);

        $result = $this->callService->makeExotelCall(
            Auth::user(),
            $request->phone,
            (int) $request->lead_id
        );

        if (!$result['success']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 422);
        }

        return response()->json([
            'ok'          => true,
            'call_log_id' => $result['call_log_id'],
            'call_sid'    => $result['call_sid'],
            'message'     => $result['message'],
        ]);
    }

    public function voipCall(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
            'phone'   => 'required|string|max:20',
        ]);

        $voipUsername = (string) Setting::get('voip_username', '');
        $voipDomain   = (string) Setting::get('voip_domain', '');

        if ($voipUsername === '' || $voipDomain === '') {
            return response()->json([
                'ok'    => false,
                'error' => 'VOIP configuration is incomplete in Admin > Call Settings.',
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $callLog = CallLog::create([
            'lead_id'         => $request->lead_id,
            'user_id'         => Auth::id(),
            'customer_number' => $phone,
            'provider'        => 'exotel',
            'direction'       => 'outbound',
            'status'          => 'initiated',
        ]);

        return response()->json([
            'ok'          => true,
            'call_log_id' => $callLog->id,
            'status'      => 'initiated',
            'dial_to'     => 'sip:' . $phone . '@' . $voipDomain,
            // 'dial_to' => 'sip:11913@' . $voipDomain,
        ]);
    }
    public function outgoing(Request $request)
    {
        Log::info('[Exotel Outgoing]', $request->all());

        $phone = $request->input('To') ?? $request->input('phone');

        if (!$phone) {
            return response('<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Hangup/>
</Response>', 200)->header('Content-Type', 'text/xml');
        }

        $phone = preg_replace('/\D/', '', $phone);

        if (strlen($phone) == 10) {
            $phone = '91' . $phone;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Dial>' . $phone . '</Dial>
</Response>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    public function registerBrowserIncoming(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string|max:40',
            'call_sid' => 'nullable|string|max:255',
        ]);

        $phone   = $this->normalizePhone((string) $request->input('phone'));
        $callSid = (string) $request->input('call_sid', '');
        $lead    = $this->findLeadByPhone($phone);

        $callLog = null;

        if ($callSid !== '') {
            $callLog = CallLog::query()
                ->where('provider', 'exotel')
                ->where('direction', 'inbound')
                ->where('call_sid', $callSid)
                ->latest('id')
                ->first();
        }

        if ($callLog) {
            $callLog->update([
                'lead_id'         => $callLog->lead_id ?: $lead?->id,
                'user_id'         => $callLog->user_id ?: Auth::id(),
                'customer_number' => $phone,
                'status'          => 'ringing',
            ]);
        } else {
            $callLog = CallLog::create([
                'lead_id'         => $lead?->id,
                'user_id'         => Auth::id(),
                'customer_number' => $phone,
                'provider'        => 'exotel',
                'direction'       => 'inbound',
                'call_sid'        => $callSid !== '' ? $callSid : null,
                'status'          => 'ringing',
            ]);
        }

        return response()->json([
            'ok'          => true,
            'call_log_id' => $callLog->id,
            'phone'       => $callLog->customer_number,
            'lead_name'   => $callLog->lead?->name,
            'lead_url'    => $this->resolveLeadUrl($callLog->lead_id),
        ]);
    }

    public function webhook(Request $request)
    {
        Log::info('[Exotel Webhook]', $request->all());

        $callSid      = (string) ($request->input('CallSid')
            ?? $request->input('call_sid')
            ?? $request->input('Sid')
            ?? $request->input('sid')
            ?? '');
        $status       = strtolower((string) ($request->input('Status')
            ?? $request->input('status')
            ?? $request->input('CallStatus')
            ?? ''));
        $duration     = $request->input('Duration', $request->input('duration', $request->input('CallDuration')));
        $recordingUrl = $request->input('RecordingUrl') ?? $request->input('recording_url');

        $call = $callSid !== ''
            ? CallLog::query()
            ->where('provider', 'exotel')
            ->where('call_sid', $callSid)
            ->latest('id')
            ->first()
            : null;

        if (!$call) {
            Log::warning('[Exotel Webhook] Call log not found', ['callSid' => $callSid]);
            return response('OK', 200);
        }

        $updates = [];
        $resolvedStatus = $this->resolveWebhookStatus($status, $call->direction);

        if ($resolvedStatus !== '') {
            $updates['status'] = $resolvedStatus;
        }

        if (in_array($status, ['in-progress', 'answered'], true) && !$call->answered_at) {
            $updates['answered_at'] = Carbon::now('Asia/Kolkata');
        }

        if ($duration !== null && $duration !== '') {
            $updates['duration'] = (int) $duration;
        }

        if ($recordingUrl) {
            $updates['recording_url'] = $recordingUrl;
        }

        if (in_array($status, ['completed', 'failed', 'busy', 'no-answer', 'canceled'], true)) {
            if (!$call->ended_at) {
                $updates['ended_at'] = Carbon::now('Asia/Kolkata');
            }

            $updates['end_reason'] = $status;

            if (!$call->ended_by) {
                $updates['ended_by'] = $status === 'canceled' ? 'telecaller' : 'unknown';
            }
        }

        $call->update($updates);

        return response('OK', 200);
    }

    public function incomingConnect(Request $request): \Illuminate\Http\Response
    {
        Log::info('[Exotel incomingConnect]', $request->all());

        $fromRaw    = (string) $request->input('From', '');
        $callSid    = (string) $request->input('CallSid', '');
        $fromPhone  = $this->normalizePhone($fromRaw);
        $lead       = $this->findLeadByPhone($fromPhone);
        $telecaller = $this->resolveInboundTelecaller($lead);

        $callLog = CallLog::create([
            'lead_id'         => $lead?->id,
            'user_id'         => $telecaller?->id,
            'customer_number' => $fromPhone,
            'provider'        => 'exotel',
            'direction'       => 'inbound',
            'call_sid'        => $callSid !== '' ? $callSid : null,
            'status'          => $telecaller ? 'ringing' : 'missed',
        ]);

        if (!$telecaller) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Response>'
                . '<Say language="en-IN">Sorry, all agents are currently unavailable. Please call back later.</Say>'
                . '<Hangup/>'
                . '</Response>';

            return response($xml, 200)->header('Content-Type', 'text/xml');
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    public function incomingPoll()
    {
        $callLog = CallLog::with('lead:id,name,lead_code')
            ->where('provider', 'exotel')
            ->where('user_id', Auth::id())
            ->where('direction', 'inbound')
            ->where('status', 'ringing')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->latest('id')
            ->first();

        if (!$callLog) {
            return response()->json(['has_incoming' => false]);
        }

        return response()->json([
            'has_incoming' => true,
            'call_log_id'  => $callLog->id,
            'phone'        => $callLog->customer_number,
            'lead_name'    => $callLog->lead?->name,
            'lead_code'    => $callLog->lead?->lead_code,
            'lead_url'     => $this->resolveLeadUrl($callLog->lead_id),
        ]);
    }

    public function status(int $callLogId)
    {
        $call = CallLog::where('id', $callLogId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'status'      => $call->status,
            'duration'    => $call->duration,
            'answered_at' => $call->answered_at?->toIso8601String(),
        ]);
    }

    public function voipConfig()
    {
        $enabled = (bool) Setting::get('voip_enabled', '0');

        if (!$enabled) {
            return response()->json(['enabled' => false]);
        }

        return response()->json([
            'enabled'  => true,
            'domain'   => (string) Setting::get('voip_domain', ''),
            'proxy'    => $this->sanitizeVoipProxy((string) Setting::get('voip_proxy', 'voip.in1.exotel.com')),
            'username' => (string) Setting::get('voip_username', ''),
            'password' => Setting::getSecure('voip_password', ''),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        return $digits;
    }

    private function comparablePhone(string $phone): ?string
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return null;
        }

        return strlen($normalized) >= 10 ? substr($normalized, -10) : $normalized;
    }

    private function findLeadByPhone(string $phone): ?Lead
    {
        $needle = $this->comparablePhone($phone);

        if (!$needle) {
            return null;
        }

        $directMatch = Lead::query()
            ->where('phone', 'like', '%' . $needle)
            ->latest('id')
            ->first();

        if ($directMatch && $this->comparablePhone((string) $directMatch->phone) === $needle) {
            return $directMatch;
        }

        return Lead::query()
            ->latest('id')
            ->get()
            ->first(fn(Lead $lead) => $this->comparablePhone((string) $lead->phone) === $needle);
    }

    private function resolveInboundTelecaller(?Lead $lead): ?User
    {
        $this->markStaleTelecallersOffline();

        if ($lead && $lead->assigned_to) {
            $assigned = $this->onlineTelecallersQuery()
                ->where('id', $lead->assigned_to)
                ->first();

            if ($assigned) {
                return $assigned;
            }
        }

        return $this->onlineTelecallersQuery()
            ->orderByDesc('last_seen_at')
            ->first();
    }

    private function onlineTelecallersQuery()
    {
        $query = User::query()
            ->where('role', 'telecaller')
            ->where('status', 1);

        if (Schema::hasColumn('users', 'is_online')) {
            $query->where('is_online', true);
        }

        if (Schema::hasColumn('users', 'last_seen_at')) {
            $query->where('last_seen_at', '>=', now()->subSeconds(90));
        }

        return $query;
    }

    private function markStaleTelecallersOffline(): void
    {
        if (!Schema::hasColumn('users', 'is_online') || !Schema::hasColumn('users', 'last_seen_at')) {
            return;
        }

        User::where('role', 'telecaller')
            ->where('is_online', true)
            ->where('last_seen_at', '<', now()->subSeconds(90))
            ->update(['is_online' => false]);
    }

    private function resolveWebhookStatus(string $status, ?string $direction): string
    {
        if ($direction === 'inbound' && in_array($status, ['busy', 'no-answer'], true)) {
            return 'missed';
        }

        return $status;
    }

    private function sanitizeVoipProxy(string $proxy): string
    {
        $proxy = trim($proxy);

        if ($proxy === '') {
            return 'voip.in1.exotel.com';
        }

        $proxy = preg_replace('#^wss?://#i', '', $proxy);
        $proxy = rtrim($proxy, '/');
        $proxy = preg_replace('/:443$/', '', $proxy);

        return $proxy !== '' ? $proxy : 'voip.in1.exotel.com';
    }

    private function resolveLeadUrl(?int $leadId): ?string
    {
        if (!$leadId || !Auth::check()) {
            return null;
        }

        return match (Auth::user()->role) {
            'telecaller' => route('telecaller.leads.show', encrypt($leadId)),
            'manager' => route('manager.leads.show', encrypt($leadId)),
            default => null,
        };
    }
}
