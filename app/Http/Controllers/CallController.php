<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CallLog;
use App\Models\LeadActivity;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Models\Followup;

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
            'provider' => (string) Setting::get('primary_call_provider', 'tcn'),
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
            'ended_by' => 'nullable|in:telecaller,manager,lead,customer,system,unknown',
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

            $requestedEndedBy = $request->input('ended_by');
            $meaningfulValues = ['telecaller', 'manager', 'lead', 'customer', 'system'];
            if ($request->filled('ended_by') && in_array($requestedEndedBy, $meaningfulValues)) {
                // Only overwrite if DB value is not already meaningful (prevents race with tcn-softphone.js patch)
                if (!in_array($call->ended_by, $meaningfulValues)) {
                    $updates['ended_by'] = $requestedEndedBy;
                }
            } elseif (!$call->ended_by) {
                $updates['ended_by'] = $requestedEndedBy ?? 'unknown';
            }

            if (!$call->end_reason && $resolvedStatus !== 'in-progress') {
                $updates['end_reason'] = $request->input('end_reason', $resolvedStatus);
            }

            if (!Schema::hasColumn('call_logs', 'ended_by')) {
                unset($updates['ended_by']);
            }

            $call->update($updates);

            $endedByText = $updates['ended_by'] ?? ($call->ended_by ?: 'unknown');

            if ($call->lead_id) {
                LeadActivity::create([
                    'lead_id' => $call->lead_id,
                    'user_id' => $call->user_id,
                    'type' => 'call',
                    'description' => "Call {$resolvedStatus}. Duration: {$duration} seconds. Ended by: {$endedByText}.",
                    'activity_time' => now()
                ]);
            }
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

}
