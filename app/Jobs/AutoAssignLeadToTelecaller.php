<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\AutomationSettings;
use App\Services\LeadAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoAssignLeadToTelecaller implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutomationSettings $settings, LeadAssignmentService $service): void
    {
        if (!$settings->autoAssignTelecallerEnabled()) {
            return;
        }

        $cutoff = now()->subHours($settings->autoAssignTelecallerHours());

        $leads = Lead::whereNotNull('assigned_by')
            ->whereNull('assigned_to')
            ->where('manager_assigned_at', '<=', $cutoff)
            ->get();

        if ($leads->isEmpty()) {
            return;
        }

        foreach ($leads as $lead) {
            $telecallerId = $service->roundRobinTelecaller();

            if (!$telecallerId) {
                Log::warning('[AutoAssignLeadToTelecaller] No active telecallers available.');
                break;
            }

            $lead->assigned_to = $telecallerId;
            $lead->status      = 'assigned';
            $lead->save();

            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => null,
                'type'          => 'note',
                'description'   => 'Auto-assigned to telecaller by system (manager did not assign within deadline).',
                'activity_time' => now(),
            ]);

            Log::info("[AutoAssignLeadToTelecaller] Lead #{$lead->id} auto-assigned to telecaller #{$telecallerId}.");
        }
    }
}
