<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Notifications\LeadAssignmentNotification;
use App\Services\LeadDefaults;
use App\Services\ManagerLeadAllocator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeadCaptureController extends Controller
{
    public function __construct(private ManagerLeadAllocator $managerLeadAllocator)
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'course' => 'required|string',
        ]);

        $managerId = $this->managerLeadAllocator->resolveManagerIdForIncomingLead();

        $courseId = Course::where('name', trim($request->course))->value('id');

        $lead = Lead::create([
            'lead_code' => $this->generateLeadCode(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'course_id' => $courseId,
            'source' => 'Landing Page',
            'assigned_by' => $managerId,
            'status' => LeadDefaults::defaultStatus(),
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => null,
            'type' => 'note',
            'description' => 'Lead captured from Landing Page',
            'meta_data' => null,
            'activity_time' => Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s'),
        ]);

        if ($managerId) {
            $manager = \App\Models\User::find($managerId);
            if ($manager) {
                $manager->notify(new LeadAssignmentNotification(
                    title: 'New Lead Assigned',
                    message: 'Lead ' . $lead->lead_code . ' auto-assigned to you.',
                    link: route('manager.leads.show', encrypt($lead->id)),
                    meta: ['type' => 'lead_assignment', 'lead_id' => $lead->id]
                ));
            }

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => null,
                'type' => 'assignment',
                'description' => "Auto-assigned to manager #{$managerId}",
                'meta_data' => ['manager_id' => $managerId],
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

    private function generateLeadCode(): string
    {
        $prefix = 'SMIT';

        $lastLead = Lead::latest('id')->first();
        $nextNumber = $lastLead ? $lastLead->id + 1 : 1;
        $formattedNumber = str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

        return $prefix . '-' . $formattedNumber;
    }
}
