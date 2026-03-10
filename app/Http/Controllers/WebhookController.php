<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CallLog;

class WebhookController extends Controller
{


    public function exotel(Request $request)
    {
        $callSid = $request->input('CallSid');

        $callLog = CallLog::where('call_sid', $callSid)->first();

        if ($callLog) {
            $callLog->update([
                'status' => $request->input('CallStatus'),
                'duration' => $request->input('DialCallDuration'),
                'recording_url' => $request->input('RecordingUrl')
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
