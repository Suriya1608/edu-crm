<?php

namespace App\Console\Commands;

use App\Jobs\DispatchEscalations;
use App\Jobs\DispatchFollowupReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches automation jobs to the queue.
 * Registered in routes/console.php to run every minute via the scheduler.
 *
 * Usage:
 *   php artisan crm:run-automation
 *
 * Queue worker must be running separately:
 *   php artisan queue:work --queue=default --tries=2
 */
class RunAutomation extends Command
{
    protected $signature   = 'crm:run-automation';
    protected $description = 'Dispatch escalation and follow-up reminder jobs to the queue';

    public function handle(): int
    {
        $this->info('Dispatching automation jobs...');
        Log::channel('single')->info('[RunAutomation] Dispatching DispatchEscalations and DispatchFollowupReminders jobs.');

        DispatchEscalations::dispatch();
        DispatchFollowupReminders::dispatch();

        $this->info('Automation jobs dispatched successfully.');
        return self::SUCCESS;
    }
}
