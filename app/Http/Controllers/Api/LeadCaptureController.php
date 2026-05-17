<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Notifications\LeadAssignmentNotification;
use App\Services\LeadCodeGenerator;
use App\Services\LeadDefaults;
use App\Services\LeadAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeadCaptureController extends Controller
{
    public function __construct(private LeadAssignmentService $leadAssignment) {}

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email',
            'phone'    => 'required|string|max:20',
            'course'   => 'required|string|max:255',
            'gender'   => 'nullable|in:male,female,other',
            'dob'      => 'nullable|date|before:today',
            'address'  => 'nullable|string|max:500',
            'city'     => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
            'pincode'  => 'nullable|string|max:10',
        ]);

        $phone = $request->phone;
        if (!str_starts_with($phone, '+91')) {
            $phone = '+91' . ltrim($phone, '0');
        }

        $duplicate = Lead::where('email', $request->email)
            ->orWhere('phone', $phone)
            ->first();

        if ($duplicate) {
            $fields = [];
            if ($duplicate->email === $request->email) {
                $fields[] = 'email';
            }
            if ($duplicate->phone === $phone) {
                $fields[] = 'mobile number';
            }
            return response()->json([
                'success' => false,
                'message' => 'The ' . implode(' and ', $fields) . ' already exists.',
            ], 409)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // Exact match — LIKE '%...%' prevents index use and is injection-prone
        $courseId = Course::where('name', trim($request->course))->value('id');

        // Two-step creation: placeholder first, then derive code from actual ID
        $lead = Lead::create([
            'lead_code'       => LeadCodeGenerator::placeholder(),
            'name'            => $request->name,
            'email'           => $request->email,
            'phone'           => $phone,
            'gender'          => $request->gender ?: null,
            'dob'             => $request->dob ?: null,
            'address'         => $request->address ?: null,
            'city'            => $request->city ?: null,
            'district'        => $request->district ?: null,
            'state'           => $request->state ?: null,
            'pincode'         => $request->pincode ?: null,
            'course_id'       => $courseId,
            'academic_year_id'=> AcademicYear::current()?->id,
            'source'          => 'Landing Page',
            'source_type'     => 'landing_page',
            'source_category' => 'website',
            'source_detail'   => $request->input('utm_source'),
            'status'          => LeadDefaults::defaultStatus(),
        ]);

        // Derive final code from auto-increment ID — race-condition free
        LeadCodeGenerator::assignCode($lead);

        $this->leadAssignment->assignIncomingLead($lead);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => null,
            'type'          => ActivityType::Note->value,
            'description'   => 'Lead captured from Landing Page',
            'meta_data'     => null,
            'activity_time' => Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s'),
        ]);

        $managerId = $lead->assigned_by;
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
