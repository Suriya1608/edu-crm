<?php

namespace App\Console\Commands;

use App\Jobs\SendDailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches the daily telecaller summary email job to the queue.
 * Scheduled to run at 19:00 daily via routes/console.php.
 *
 * Usage:
 *   php artisan crm:daily-summary
 */
class SendDailySummaryCommand extends Command
{
    protected $signature   = 'crm:daily-summary';
    protected $description = 'Dispatch daily telecaller performance summary emails to managers';

    public function handle(): int
    {
        $this->info('Dispatching daily summary job...');
        Log::channel('single')->info('[DailySummary] Dispatching SendDailySummary job.');

        SendDailySummary::dispatch();

        $this->info('Daily summary job dispatched.');
        return self::SUCCESS;
    }
}
