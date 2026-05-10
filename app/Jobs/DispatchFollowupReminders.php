<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasTenantContext;
use App\Services\AutomationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DispatchFollowupReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasTenantContext;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct()
    {
        $this->initTenantContext();
    }

    public function handle(AutomationEngine $engine): void
    {
        $this->switchTenantConnection();

        $lockKey = 'crm_followup_reminder_run_' . $this->tenantId;
        $lock = Cache::lock($lockKey, 300);

        if (!$lock->get()) {
            Log::channel('single')->info('[FollowupReminders] Skipped — previous run still active.');
            return;
        }

        try {
            Log::channel('single')->info('[FollowupReminders] Dispatching telecaller follow-up reminders.');
            // Pass null so reminders are dispatched for ALL telecallers
            $engine->dispatchTelecallerFollowupReminders(null);
            Log::channel('single')->info('[FollowupReminders] Reminder dispatch complete.');
        } finally {
            $lock->release();
        }
    }
}
