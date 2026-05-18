<?php

namespace App\Console\Commands;

use App\Jobs\AutoAssignLeadToTelecaller;
use App\Jobs\DispatchEscalations;
use App\Jobs\DispatchFollowupReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAutomation extends Command
{
    protected $signature   = 'crm:run-automation';
    protected $description = 'Dispatch escalation and follow-up reminder jobs';

    public function handle(): int
    {
        dispatch(new DispatchEscalations());
        dispatch(new DispatchFollowupReminders());
        dispatch(new AutoAssignLeadToTelecaller());

        Log::channel('single')->info('[RunAutomation] Automation jobs dispatched.');
        $this->info('Automation jobs dispatched.');
        return self::SUCCESS;
    }
}
