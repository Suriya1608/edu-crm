<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseManagerAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Services\AutomationSettings;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function __construct(private AutomationSettings $automationSettings)
    {
    }

    public function leadAssignment()
    {
        $courses  = Course::orderBy('name')->get(['id', 'name']);
        $managers = User::where('role', 'manager')->orderBy('name')->get(['id', 'name']);
        $mappings = CourseManagerAssignment::with(['course:id,name', 'manager:id,name'])->get();

        return view('admin.automation.lead-assignment', [
            'values' => [
                'enabled'              => $this->automationSettings->leadAssignmentEnabled(),
                'active_only'          => $this->automationSettings->assignToActiveManagersOnly(),
                'mode'                 => $this->automationSettings->leadAssignmentMode(),
                'auto_assign_tc'       => $this->automationSettings->autoAssignTelecallerEnabled(),
                'auto_assign_tc_hours' => $this->automationSettings->autoAssignTelecallerHours(),
            ],
            'courses'  => $courses,
            'managers' => $managers,
            'mappings' => $mappings,
        ]);
    }

    public function updateLeadAssignment(Request $request)
    {
        $request->validate([
            'enabled'              => 'nullable|boolean',
            'active_only'          => 'nullable|boolean',
            'mode'                 => 'required|in:round_robin,course_based,open_pool',
            'auto_assign_tc'       => 'nullable|boolean',
            'auto_assign_tc_hours' => 'required|integer|min:1|max:720',
        ]);

        Setting::set(AutomationSettings::LEAD_ASSIGNMENT_ENABLED, $request->boolean('enabled') ? '1' : '0');
        Setting::set(AutomationSettings::LEAD_ASSIGN_ACTIVE_ONLY, $request->boolean('active_only') ? '1' : '0');
        Setting::set(AutomationSettings::LEAD_ASSIGNMENT_MODE, $request->input('mode'));
        Setting::set(AutomationSettings::LEAD_AUTO_ASSIGN_TC_ENABLED, $request->boolean('auto_assign_tc') ? '1' : '0');
        Setting::set(AutomationSettings::LEAD_AUTO_ASSIGN_TC_HOURS, (string) $request->integer('auto_assign_tc_hours'));

        return back()->with('success', 'Lead assignment rules updated.');
    }

    public function storeCourseMapping(Request $request)
    {
        $request->validate([
            'course_id'  => 'required|exists:courses,id',
            'manager_id' => 'required|exists:users,id',
        ]);

        CourseManagerAssignment::updateOrCreate(
            ['course_id' => $request->course_id, 'manager_id' => $request->manager_id],
            ['is_active' => true]
        );

        return back()->with('success', 'Course mapping added.');
    }

    public function toggleCourseMapping(CourseManagerAssignment $mapping)
    {
        $mapping->update(['is_active' => !$mapping->is_active]);
        return back()->with('success', 'Mapping ' . ($mapping->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroyCourseMapping(CourseManagerAssignment $mapping)
    {
        $mapping->delete();
        return back()->with('success', 'Course mapping removed.');
    }

    public function followupReminder()
    {
        return view('admin.automation.followup-reminder', [
            'values' => [
                'enabled' => $this->automationSettings->followupReminderEnabled(),
                'days_before' => $this->automationSettings->followupReminderDaysBefore(),
                'highlight_overdue' => $this->automationSettings->followupOverdueHighlightEnabled(),
                'daily_summary_email_enabled' => Setting::get('daily_summary_email_enabled', '0') === '1',
            ],
        ]);
    }

    public function updateFollowupReminder(Request $request)
    {
        $request->validate([
            'enabled' => 'nullable|boolean',
            'days_before' => 'required|integer|min:0|max:30',
            'highlight_overdue' => 'nullable|boolean',
            'daily_summary_email_enabled' => 'nullable|boolean',
        ]);

        Setting::set(AutomationSettings::FOLLOWUP_REMINDER_ENABLED, $request->boolean('enabled') ? '1' : '0');
        Setting::set(AutomationSettings::FOLLOWUP_REMINDER_DAYS_BEFORE, (string) $request->integer('days_before'));
        Setting::set(AutomationSettings::FOLLOWUP_OVERDUE_HIGHLIGHT, $request->boolean('highlight_overdue') ? '1' : '0');
        Setting::set('daily_summary_email_enabled', $request->boolean('daily_summary_email_enabled') ? '1' : '0');

        return back()->with('success', 'Follow-up reminder rules updated.');
    }

    public function escalation()
    {
        return view('admin.automation.escalation', [
            'values' => [
                'enabled' => $this->automationSettings->escalationEnabled(),
                'missed_followups' => $this->automationSettings->escalateMissedFollowupsEnabled(),
                'response_sla' => $this->automationSettings->escalateResponseSlaEnabled(),
                'response_sla_minutes' => $this->automationSettings->responseSlaMinutes(),
            ],
        ]);
    }

    public function updateEscalation(Request $request)
    {
        $request->validate([
            'enabled' => 'nullable|boolean',
            'missed_followups' => 'nullable|boolean',
            'response_sla' => 'nullable|boolean',
            'response_sla_minutes' => 'required|integer|min:5|max:10080',
        ]);

        Setting::set(AutomationSettings::ESCALATION_ENABLED, $request->boolean('enabled') ? '1' : '0');
        Setting::set(AutomationSettings::ESCALATE_MISSED_FOLLOWUPS, $request->boolean('missed_followups') ? '1' : '0');
        Setting::set(AutomationSettings::ESCALATE_RESPONSE_SLA, $request->boolean('response_sla') ? '1' : '0');
        Setting::set(AutomationSettings::RESPONSE_SLA_MINUTES, (string) $request->integer('response_sla_minutes'));

        return back()->with('success', 'Escalation rules updated.');
    }
}
