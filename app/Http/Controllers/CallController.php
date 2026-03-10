<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CallLog;
use App\Models\LeadActivity;
use App\Models\Lead;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Twilio\TwiML\VoiceResponse;
class CallController extends Controller
{
    public function startCall(Request $request)
    {
        $lead = Lead::find($request->lead_id);
        $customerNumber = $request->input('customer_number', $lead?->phone);

        $call = CallLog::create([
            'lead_id' => $request->lead_id,
            'user_id' => Auth::id(),
            'customer_number' => $customerNumber,
            'provider' => (string) Setting::get('telephony_provider', 'twilio'),
            'direction' => 'outbound',
            'status' => 'ringing'
        ]);

        return response()->json([
            'call_log_id' => $call->id
        ]);
    }
    public function endCall(Request $request)
    {
        $request->validate([
            'call_log_id' => 'required|integer',
            'ended_by' => 'nullable|in:telecaller,customer,system,unknown',
            'final_status' => 'nullable|in:initiated,ringing,in-progress,answered,completed,busy,failed,no-answer,canceled',
            'end_reason' => 'nullable|string|max:50',
            'duration' => 'nullable|integer|min:0',
        ]);

        $call = CallLog::find($request->call_log_id);

        if ($call) {
            $duration = null;

            // Prefer server-side answered_at when available.
            if ($call->duration !== null) {
                $duration = (int) $call->duration;
            }

            if ($duration === null && $request->filled('duration')) {
                $duration = (int) $request->input('duration');
            }

            if ($duration === null && $call->answered_at) {
                $duration = Carbon::parse($call->answered_at)->diffInSeconds(Carbon::now('Asia/Kolkata'));
            }

            // Do NOT use initiated time (created_at), that overstates talk-time.
            if ($duration === null) {
                $duration = 0;
            }

            $resolvedStatus = $request->input('final_status', $call->status);
            if (empty($resolvedStatus)) {
                if ($duration > 0) {
                    $resolvedStatus = 'completed';
                } else {
                    $endedBy = $request->input('ended_by', 'unknown');
                    $resolvedStatus = $endedBy === 'telecaller' ? 'canceled' : 'no-answer';
                }
            }

            $updates = [
                'duration' => $duration,
                'status' => $resolvedStatus,
            ];

            if (!$call->ended_at) {
                $updates['ended_at'] = now('Asia/Kolkata');
            }

            if ($request->filled('ended_by')) {
                $updates['ended_by'] = $request->input('ended_by');
            } elseif (!$call->ended_by) {
                $updates['ended_by'] = 'unknown';
            }

            if (!$call->end_reason && $resolvedStatus !== 'in-progress') {
                $updates['end_reason'] = $request->input('end_reason', $resolvedStatus);
            }

            if (!Schema::hasColumn('call_logs', 'ended_by')) {
                unset($updates['ended_by']);
            }

            $call->update($updates);

            $endedByText = $updates['ended_by'] ?? ($call->ended_by ?: 'unknown');

            LeadActivity::create([
                'lead_id' => $call->lead_id,
                'user_id' => $call->user_id,
                'type' => 'call',
                'description' => "Call {$resolvedStatus}. Duration: {$duration} seconds. Ended by: {$endedByText}.",
                'activity_time' => now()
            ]);
        }

        return response()->json(['ok']);
    }

    /**
     * Record the agent-selected outcome after a call ends.
     * If outcome = call_back_later, automatically creates a follow-up for tomorrow.
     */
    public function recordOutcome(Request $request)
    {
        $request->validate([
            'call_log_id' => 'required|integer',
            'outcome'     => 'required|in:interested,not_interested,wrong_number,call_back_later,switched_off',
        ]);

        $call = CallLog::find($request->call_log_id);
        if (!$call) {
            return response()->json(['ok' => false, 'message' => 'Call log not found.'], 404);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('call_logs', 'outcome')) {
            $call->update(['outcome' => $request->outcome]);
        }

        // Log outcome to activity timeline
        $outcomeLabel = str_replace('_', ' ', ucfirst($request->outcome));
        LeadActivity::create([
            'lead_id'       => $call->lead_id,
            'user_id'       => $call->user_id,
            'type'          => 'call',
            'description'   => "Call outcome recorded: {$outcomeLabel}.",
            'meta_data'     => json_encode(['outcome' => $request->outcome, 'call_log_id' => $call->id]),
            'activity_time' => now(),
        ]);

        // Auto-create follow-up for "Call Back Later" outcome
        if ($request->outcome === 'call_back_later' && $call->lead_id) {
            \App\Models\Followup::create([
                'lead_id'      => $call->lead_id,
                'user_id'      => $call->user_id,
                'remarks'      => 'Auto-created from call outcome: Call Back Later.',
                'next_followup' => now()->addDay()->toDateString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function updateCallSid(Request $request)
    {
        CallLog::where('id', $request->call_log_id)
            ->update([
                'call_sid' => $request->call_sid,
            ]);

        return response()->json(['ok']);
    }

    public function incomingCall(Request $request)
    {
        $fromRaw = (string) $request->input('From', '');
        $fromDigits = preg_replace('/\D+/', '', $fromRaw);
        $last10 = $fromDigits ? substr($fromDigits, -10) : null;

        $lead = null;
        if ($last10) {
            $lead = Lead::where('phone', 'like', '%' . $last10)->latest('id')->first();
        }

        $lastOutbound = null;
        if ($lead) {
            $lastOutbound = CallLog::where('lead_id', $lead->id)
                ->where('direction', 'outbound')
                ->latest('id')
                ->first();
        }

        $telecallerId = $lastOutbound?->user_id;

        if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            User::where('role', 'telecaller')
                ->where('is_online', true)
                ->where('last_seen_at', '<', now()->subSeconds(90))
                ->update(['is_online' => false]);
        }

        $telecallerOnline = false;
        if ($telecallerId) {
            $query = User::where('id', $telecallerId)
                ->where('role', 'telecaller')
                ->where('status', 1); // account active

            if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
                $query->where('is_online', true)
                    ->where('last_seen_at', '>=', now()->subSeconds(90));
            } else {
                $query->whereRaw('1 = 0');
            }

            $telecallerOnline = $query->exists();
        }

        $call = CallLog::create([
            'lead_id' => $lead?->id,
            'user_id' => $telecallerId,
            'customer_number' => $fromRaw,
            'provider' => (string) Setting::get('telephony_provider', 'twilio'),
            'direction' => 'inbound',
            'call_sid' => $request->input('CallSid'),
            'status' => $telecallerOnline ? 'ringing' : 'missed',
        ]);

        $response = new VoiceResponse();

        if ($telecallerOnline) {
            $dial = $response->dial('', [
                'action' => route('webhook.call-status', ['call_log_id' => $call->id]),
                'method' => 'POST',
            ]);
            $dial->client('agent_' . $telecallerId);
        } else {
            $response->say('Our executive is currently unavailable. We will call you back.', ['voice' => 'alice']);
        }

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }

    public function callStatusWebhook(Request $request)
    {
        $callLogId = $request->input('call_log_id');
        $callSid = $request->input('CallSid');
        $callStatus = strtolower((string) $request->input('CallStatus', ''));
        $dialCallStatus = strtolower((string) $request->input('DialCallStatus', ''));

        $status = $dialCallStatus ?: $callStatus;
        $resolvedStatus = in_array($status, ['completed'], true) ? 'completed' : 'missed';

        $call = null;
        if ($callLogId) {
            $call = CallLog::find($callLogId);
        }
        if (!$call && $callSid) {
            $call = CallLog::where('call_sid', $callSid)->latest('id')->first();
        }

        if ($call) {
            $updates = [
                'status' => $resolvedStatus,
                'end_reason' => $status ?: $resolvedStatus,
                'ended_at' => now(),
            ];

            $duration = $request->input('CallDuration', $request->input('DialCallDuration'));
            if ($duration !== null && $duration !== '') {
                $updates['duration'] = (int) $duration;
            }

            $call->update($updates);
        }

        return response('OK', 200);
    }
}
