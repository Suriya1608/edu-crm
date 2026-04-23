<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Notifications\LeadAssignmentNotification;
use App\Services\LeadCodeGenerator;
use App\Services\LeadDefaults;
use App\Services\ManagerLeadAllocator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeadCaptureController extends Controller
{
    public function __construct(private ManagerLeadAllocator $managerLeadAllocator) {}

    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email',
            'phone'  => 'required|string|max:20',
            'course' => 'required|string|max:255',
        ]);

        $managerId = $this->managerLeadAllocator->resolveManagerIdForIncomingLead();

        // Exact match — LIKE '%...%' prevents index use and is injection-prone
        $courseId = Course::where('name', trim($request->course))->value('id');

        // Two-step creation: placeholder first, then derive code from actual ID
        $lead = Lead::create([
            'lead_code'   => LeadCodeGenerator::placeholder(),
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'course_id'   => $courseId,
            'source'      => 'Landing Page',
            'assigned_by' => $managerId,
            'status'      => LeadDefaults::defaultStatus(),
        ]);

        // Derive final code from auto-increment ID — race-condition free
        LeadCodeGenerator::assignCode($lead);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => null,
            'type'          => ActivityType::Note->value,
            'description'   => 'Lead captured from Landing Page',
            'meta_data'     => null,
            'activity_time' => Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s'),
        ]);

        if ($managerId) {
            $manager = \App\Models\User::find($managerId);
            if ($manager) {
                $manager->notify(new LeadAssignmentNotification(
                    title:   'New Lead Assigned',
                    message: 'Lead ' . $lead->lead_code . ' auto-assigned to you.',
                    link:    route('manager.leads.show', encrypt($lead->id)),
                    meta:    ['type' => 'lead_assignment', 'lead_id' => $lead->id]
                ));
            }

            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => null,
                'type'          => ActivityType::Assignment->value,
                'description'   => "Auto-assigned to manager #{$managerId}",
                'meta_data'     => ['manager_id' => $managerId],
                'activity_time' => Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead stored successfully',
        ])->withHeaders([
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
