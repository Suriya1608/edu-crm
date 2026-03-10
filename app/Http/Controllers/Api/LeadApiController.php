<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Notifications\LeadAssignmentNotification;
use App\Services\ManagerLeadAllocator;
use App\Services\LeadDefaults;
use Illuminate\Http\Request;

class LeadApiController extends Controller
{
    public function __construct(private ManagerLeadAllocator $managerLeadAllocator)
    {
    }

    public function store(Request $request)
    {
        if ($request->header('X-API-KEY') !== env('LEAD_API_KEY')) {
            return response()->json([
                'error' => 'Unauthorized Access',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'course' => 'nullable|string',
            'source' => 'nullable|string',
        ]);

        $exists = Lead::where('phone', $request->phone)->first();
        if ($exists) {
            return response()->json([
                'message' => 'Lead already exists',
            ]);
        }

        $managerId = $this->managerLeadAllocator->resolveManagerIdForIncomingLead();

        $lead = Lead::create([
            'lead_code' => $this->generateLeadCode(),
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'course' => $request->course,
            'source' => $request->source ?? 'landing_page',
            'assigned_by' => $managerId,
            'status' => LeadDefaults::defaultStatus(),
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => null,
            'type' => 'note',
            'description' => 'Lead captured from API',
            'meta_data' => null,
            'activity_time' => now(),
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
                'activity_time' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Lead stored successfully',
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
