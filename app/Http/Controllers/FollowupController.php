<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;

class FollowupController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'lead_id'       => 'required|exists:leads,id',
            'next_followup' => 'required|date',
            'followup_time' => 'nullable|date_format:H:i',
            'remarks'       => 'nullable|string',
        ]);

        $lead = Lead::findOrFail($request->lead_id);

        Followup::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'remarks'       => $request->remarks,
            'next_followup' => $request->next_followup,
            'followup_time' => $request->followup_time,
        ]);

        $lead->next_followup = $request->next_followup;
        $lead->save();

        $timeStr = $request->followup_time ? ' at ' . date('h:i A', strtotime($request->followup_time)) : '';

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'type'          => 'followup',
            'description'   => 'Follow-up scheduled for ' . $request->next_followup . $timeStr,
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Follow-up scheduled successfully.');
    }
}
