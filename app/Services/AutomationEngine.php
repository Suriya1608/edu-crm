<?php

namespace App\Services;

use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\MissedFollowupEscalationNotification;
use App\Notifications\SlaViolationEscalationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class AutomationEngine
{
    public function __construct(private AutomationSettings $settings)
    {
    }

    public function runEscalations(): void
    {
        if (!$this->settings->escalationEnabled()) {
            return;
        }
        if (!$this->settings->isOperationalNow()) {
            return;
        }

        if ($this->settings->escalateMissedFollowupsEnabled()) {
            $this->escalateMissedFollowups();
        }

        if ($this->settings->escalateResponseSlaEnabled()) {
            $this->escalateResponseSlaBreaches();
        }
    }

    public function dispatchTelecallerFollowupReminders(?int $telecallerId = null): void
    {
        if (!$this->settings->followupReminderEnabled()) {
            return;
        }
        if (!$this->settings->isOperationalNow()) {
            return;
        }

        if (!Schema::hasColumn('followups', 'reminder_notified_at')) {
            return;
        }

        $daysBefore = $this->settings->followupReminderDaysBefore();
        $endDate = now()->addDays($daysBefore)->toDateString();

        $query = Followup::with('lead:id,name,lead_code,assigned_to')
            ->whereDate('next_followup', '<=', $endDate)
            ->whereDate('next_followup', '>=', now()->toDateString())
            ->whereNull('reminder_notified_at')
            ->when(Schema::hasColumn('followups', 'completed_at'), function ($q) {
                $q->whereNull('completed_at');
            });

        if ($telecallerId) {
            $query->whereHas('lead', fn($q) => $q->where('assigned_to', $telecallerId));
        }

        $followups = $query->orderBy('next_followup')->limit(100)->get();
        if ($followups->isEmpty()) {
            return;
        }

        foreach ($followups as $followup) {
            $assigneeId = (int) ($followup->lead?->assigned_to ?? 0);
            if ($assigneeId <= 0) {
                $followup->update(['reminder_notified_at' => now()]);
                continue;
            }

            $telecaller = User::where('id', $assigneeId)->where('role', 'telecaller')->first();
            if (!$telecaller) {
                $followup->update(['reminder_notified_at' => now()]);
                continue;
            }

            $dateLabel = optional($followup->next_followup)->format('d M Y') ?: 'scheduled date';
            $leadCode = $followup->lead?->lead_code ?? ('#' . $followup->lead_id);
            $message = "Follow-up due for lead {$leadCode} on {$dateLabel}.";

            $telecaller->notify(new SlaViolationEscalationNotification(
                title: 'Follow-up Reminder',
                message: $message,
                link: route('telecaller.followups.today'),
                meta: [
                    'type' => 'followup_reminder',
                    'followup_id' => $followup->id,
                    'lead_id' => $followup->lead_id,
                ]
            ));

            $followup->update(['reminder_notified_at' => now()]);
        }
    }

    private function escalateMissedFollowups(): void
    {
        if (!Schema::hasColumn('followups', 'escalated_at') || !Schema::hasTable('notifications')) {
            return;
        }

        $candidates = Followup::with(['lead.assignedUser', 'user'])
            ->whereDate('next_followup', '<', now()->toDateString())
            ->whereNull('escalated_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $managers = User::where('role', 'manager')->where('status', 1)->get();
        if ($managers->isEmpty()) {
            return;
        }

        foreach ($candidates as $followup) {
            $telecallerId = $followup->lead?->assigned_to;
            $hadAction = false;

            if ($telecallerId) {
                $hadAction = LeadActivity::where('lead_id', $followup->lead_id)
                    ->where('user_id', $telecallerId)
                    ->whereDate('created_at', '>=', $followup->next_followup->toDateString())
                    ->exists();
            } else {
                $hadAction = LeadActivity::where('lead_id', $followup->lead_id)
                    ->whereDate('created_at', '>=', $followup->next_followup->toDateString())
                    ->exists();
            }

            if ($hadAction) {
                $followup->update(['escalated_at' => now()]);
                continue;
            }

            Notification::send($managers, new MissedFollowupEscalationNotification($followup));
            $followup->update(['escalated_at' => now()]);
        }
    }

    private function escalateResponseSlaBreaches(): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('leads', 'sla_escalated_at')) {
            return;
        }

        $this->escalateTelecallerSlaBreaches();
        $this->escalateManagerSlaBreaches();
    }

    // Level 0 → Level 1: telecaller didn't respond → notify manager
    private function escalateTelecallerSlaBreaches(): void
    {
        $slaMinutes = $this->settings->responseSlaMinutes();
        $threshold  = now()->subMinutes($slaMinutes);

        $leads = Lead::query()
            ->whereNull('sla_escalated_at')
            ->where('created_at', '<=', $threshold)
            ->whereIn('status', ['new', 'open'])
            ->whereNotNull('assigned_by')
            ->whereDoesntHave('activities', function ($q) {
                $q->whereNotNull('user_id')
                    ->whereIn('type', ['call', 'whatsapp', 'sms', 'followup', 'status_change', 'note']);
            })
            ->latest('id')
            ->limit(100)
            ->get();

        if ($leads->isEmpty()) {
            return;
        }

        $managerSlaMinutes = $this->settings->managerSlaMinutes();
        $managerIds = $leads->pluck('assigned_by')->filter()->unique()->values();
        $managers   = User::whereIn('id', $managerIds)->where('role', 'manager')->where('status', 1)->get()->keyBy('id');

        foreach ($leads as $lead) {
            $manager = $managers->get((int) $lead->assigned_by);
            if ($manager) {
                $manager->notify(new SlaViolationEscalationNotification(
                    title: 'Lead Response SLA Breach',
                    message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' was not contacted by telecaller within ' . $slaMinutes . ' minutes.',
                    link: route('manager.leads.show', encrypt($lead->id)),
                    meta: [
                        'type'      => 'response_sla',
                        'lead_id'   => $lead->id,
                        'lead_code' => $lead->lead_code,
                        'sla_level' => 1,
                        'sla_minutes' => $slaMinutes,
                    ]
                ));
            }

            $updates = ['sla_escalated_at' => now()];
            if (Schema::hasColumn('leads', 'sla_level')) {
                $updates['sla_level']              = 1;
                $updates['sla_manager_deadline_at'] = now()->addMinutes($managerSlaMinutes);
            }
            $lead->update($updates);
        }
    }

    // Level 1 → Level 2: manager didn't respond → notify admin + report_viewer
    private function escalateManagerSlaBreaches(): void
    {
        if (!Schema::hasColumn('leads', 'sla_level') || !Schema::hasColumn('leads', 'sla_manager_deadline_at')) {
            return;
        }

        $leads = Lead::query()
            ->where('sla_level', 1)
            ->where('sla_manager_deadline_at', '<=', now())
            ->whereIn('status', ['new', 'open'])
            ->latest('id')
            ->limit(100)
            ->get();

        if ($leads->isEmpty()) {
            return;
        }

        $escalatees = User::whereIn('role', ['admin', 'report_viewer'])
            ->where('status', 1)
            ->get();

        $managerSlaMinutes = $this->settings->managerSlaMinutes();

        foreach ($leads as $lead) {
            if ($escalatees->isNotEmpty()) {
                Notification::send($escalatees, new SlaViolationEscalationNotification(
                    title: 'Critical: Manager SLA Breach',
                    message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' was escalated to manager but still not contacted within ' . $managerSlaMinutes . ' minutes.',
                    link: route('admin.leads.show', encrypt($lead->id)),
                    meta: [
                        'type'      => 'manager_sla',
                        'lead_id'   => $lead->id,
                        'lead_code' => $lead->lead_code,
                        'sla_level' => 2,
                    ]
                ));
            }

            $lead->update(['sla_level' => 2]);
        }
    }
}
