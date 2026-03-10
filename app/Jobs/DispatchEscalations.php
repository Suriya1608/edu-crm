<?php

namespace App\Jobs;

use App\Services\AutomationEngine;
use App\Services\AutomationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that runs missed-followup and response-SLA escalations.
 *
 * Uses a file-cache lock (25-minute TTL) to prevent overlapping runs.
 * Safe to dispatch every minute from the scheduler — extra dispatches
 * are rejected immediately if the lock is still held.
 */
class DispatchEscalations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry once on failure, then fail permanently */
    public int $tries = 2;

    /** 5-minute timeout per attempt */
    public int $timeout = 300;

    public function handle(AutomationEngine $engine): void
    {
        $lock = Cache::lock('crm_escalation_run', 300); // 5-minute lock

        if (!$lock->get()) {
            Log::channel('single')->info('[AutomationEscalation] Skipped — previous run still active.');
            return;
        }

        try {
            Log::channel('single')->info('[AutomationEscalation] Starting escalation run.');
            $engine->runEscalations();
            Log::channel('single')->info('[AutomationEscalation] Escalation run complete.');
        } finally {
            $lock->release();
        }
    }
}
