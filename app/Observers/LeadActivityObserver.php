<?php

namespace App\Observers;

use App\Models\LeadActivity;
use Illuminate\Support\Facades\Schema;

class LeadActivityObserver
{
    /**
     * When any activity is recorded on a lead, resolve its SLA escalation.
     * Assignment actions are tracked in audit_logs (not LeadActivity), so
     * every LeadActivity record is a valid response that resets the SLA clock.
     */
    public function created(LeadActivity $activity): void
    {
        if (!Schema::hasColumn('leads', 'sla_level')) {
            return;
        }

        $lead = $activity->lead;
        if (!$lead || $lead->sla_level < 1) {
            return;
        }

        $lead->update([
            'sla_level'              => 0,
            'sla_escalated_at'       => null,
            'sla_manager_deadline_at' => null,
        ]);
    }
}
