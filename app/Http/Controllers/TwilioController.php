<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;
use Twilio\TwiML\VoiceResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\CallLog;
use App\Models\LeadActivity;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TwilioController extends Controller
{
    public function generateToken()
    {
        $identity = 'agent_' . (string) Auth::id();

        $token = new AccessToken(
            $this->twilioAccountSid(),
            $this->twilioApiKey(),
            $this->twilioApiSecret(),
            3600,
            $identity
        );

        $voiceGrant = new VoiceGrant();
        $voiceGrant->setOutgoingApplicationSid($this->twilioAppSid());
        $voiceGrant->setIncomingAllow(true);

        $token->addGrant($voiceGrant);

        return response()->json([
            'token' => $token->toJWT()
        ]);
    }



    public function voice(Request $request)
    {
        $response = new \Twilio\TwiML\VoiceResponse();

        $to = $request->input('To');
        $callLogId = $request->input('call_log_id');

        if (!$to) {
            $response->say('Invalid number');
        } else {

            $dial = $response->dial(null, [
                'callerId' => $this->twilioFrom(),
                'answerOnBridge' => 'true',
                'record' => 'record-from-answer'
            ]);

            $dial->number($to, [
                'statusCallbackEvent' => 'initiated ringing answered completed',
                'statusCallback' => route('twilio.status', ['call_log_id' => $callLogId]),
                'statusCallbackMethod' => 'POST',
            ]);
        }

        return response($response->__toString(), 200)
            ->header('Content-Type', 'text/xml');
    }

    private function twilioAccountSid(): string
    {
        return (string) Setting::getSecure('twilio_account_sid', env('TWILIO_ACCOUNT_SID'));
    }

    private function twilioApiKey(): string
    {
        return (string) Setting::getSecure('twilio_api_key', env('TWILIO_API_KEY'));
    }

    private function twilioApiSecret(): string
    {
        return (string) Setting::getSecure('twilio_api_secret', env('TWILIO_API_SECRET'));
    }

    private function twilioAppSid(): string
    {
        return (string) Setting::get('twilio_app_sid', env('TWILIO_APP_SID'));
    }

    private function twilioFrom(): string
    {
        return (string) Setting::get('twilio_from_number', env('TWILIO_FROM'));
    }
    public function status(Request $request)
    {
        // For <Dial> callbacks Twilio sends DialCall* fields; fallback to Call*.
        $callLogId = $request->input('call_log_id');
        $callSid = $request->input('DialCallSid', $request->input('CallSid'));
        $callStatus = $request->input('DialCallStatus', $request->input('CallStatus'));
        $callDuration = $request->input('DialCallDuration', $request->input('CallDuration'));
        $altSid = $request->input('CallSid');
        $dialSid = $request->input('DialCallSid');

        $sidCandidates = array_values(array_filter(array_unique([
            $callSid,
            $altSid,
            $dialSid,
        ])));

        $call = null;

        if (!empty($callLogId)) {
            $call = CallLog::find($callLogId);
        }

        if (!$call && !empty($sidCandidates)) {
            $call = CallLog::whereIn('call_sid', $sidCandidates)->latest('id')->first();
        }

        // Fallback for rare leg-SID mismatch: map callback to most recent open twilio call.
        if (!$call) {
            $call = CallLog::where('provider', 'twilio')
                ->whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered', 'no_answer'])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->latest('id')
                ->first();
        }

        if ($call) {
            $updates = [
                'status' => $callStatus
            ];

            if (!empty($sidCandidates) && !$call->call_sid) {
                $updates['call_sid'] = $sidCandidates[0];
            }
            if (!$call->direction) {
                $direction = strtolower((string) $request->input('Direction', ''));
                if ($direction === 'inbound' || $direction === 'outbound-api' || $direction === 'outbound-dial') {
                    $updates['direction'] = str_starts_with($direction, 'outbound') ? 'outbound' : 'inbound';
                }
            }

            // Mark attended moment from server callback (not browser time).
            if (in_array($callStatus, ['in-progress', 'answered'], true) && !$call->answered_at) {
                $updates['answered_at'] = Carbon::now('Asia/Kolkata');
            }

            // Twilio duration is talk time (answer -> hangup). Prefer this value.
            if ($callDuration !== null && $callDuration !== '') {
                $updates['duration'] = (int) $callDuration;
            } elseif ($callStatus === 'completed' && $call->answered_at) {
                $updates['duration'] = $call->answered_at->diffInSeconds(Carbon::now('Asia/Kolkata'));
            }

            $terminalStates = ['completed', 'busy', 'failed', 'no-answer', 'canceled'];
            if (in_array($callStatus, $terminalStates, true)) {
                $updates['ended_at'] = Carbon::now('Asia/Kolkata');
                $updates['end_reason'] = $callStatus;

                if (!$call->ended_by) {
                    if (in_array($callStatus, ['busy', 'no-answer'], true)) {
                        $updates['ended_by'] = 'customer';
                    } elseif ($callStatus === 'canceled') {
                        $updates['ended_by'] = 'telecaller';
                    } else {
                        $updates['ended_by'] = 'unknown';
                    }
                }
            }

            if (!Schema::hasColumn('call_logs', 'ended_by')) {
                unset($updates['ended_by']);
            }

            $call->update($updates);
        } else {
            Log::warning('Twilio status callback did not match any call log', [
                'sid_candidates' => $sidCandidates,
                'call_status' => $callStatus,
                'call_duration' => $callDuration,
            ]);
        }

        return response('OK', 200);
    }

    public function recording(Request $request)
    {
        $call = CallLog::where('call_sid', $request->CallSid)->first();

        if ($call) {

            $call->update([
                'recording_url' => $request->RecordingUrl,
                'duration' => $request->RecordingDuration,
                'status' => 'completed'
            ]);

            LeadActivity::create([
                'lead_id' => $call->lead_id,
                'user_id' => $call->user_id,
                'type' => 'call',
                'description' =>
                "Call completed. Duration: {$request->RecordingDuration} seconds",
                'activity_time' => now()
            ]);
        }

        return response('Saved', 200);
    }

    // dont delete
    // public function voice(Request $request)
    //     {
    //         $response = new \Twilio\TwiML\VoiceResponse();

    //         $to = $request->input('To');

    //         if (!$to) {
    //             $response->say('Invalid number');
    //         } else {

    //             $dial = $response->dial(null, [
    //                 'callerId' => env('TWILIO_FROM'),
    //                 'answerOnBridge' => 'true'
    //             ]);

    //             $dial->number($to);
    //         }

    //         return response($response->__toString(), 200)
    //             ->header('Content-Type', 'text/xml');
    //     }
}
