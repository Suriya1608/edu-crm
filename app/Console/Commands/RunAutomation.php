<?php

namespace App\Console\Commands;

use App\Jobs\AutoAssignLeadToTelecaller;
use App\Jobs\DispatchEscalations;
use App\Jobs\DispatchFollowupReminders;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAutomation extends Command
{
    protected $signature   = 'crm:run-automation';
    protected $description = 'Dispatch escalation and follow-up reminder jobs for every active tenant';

    public function handle(): int
    {
        $tenants = Tenant::on('central_mysql')->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $escalation = new DispatchEscalations();
            $escalation->tenantId = $tenant->id;
            dispatch($escalation);

            $followup = new DispatchFollowupReminders();
            $followup->tenantId = $tenant->id;
            dispatch($followup);

            $assign = new AutoAssignLeadToTelecaller();
            $assign->tenantId = $tenant->id;
            dispatch($assign);

            Log::channel('single')->info("[RunAutomation] Dispatched jobs for tenant: {$tenant->subdomain}");
        }

        $this->info("Automation jobs dispatched for {$tenants->count()} tenant(s).");
        return self::SUCCESS;
    }
}
