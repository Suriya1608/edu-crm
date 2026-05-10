<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate
                            {--tenant= : Run only for this subdomain}
                            {--force   : Force migrations in production}
                            {--fresh   : Drop all tables and re-migrate (DESTRUCTIVE)}';

    protected $description = 'Run migrations across all (or a specific) tenant database(s)';

    public function handle(): int
    {
        $filter  = $this->option('tenant');
        $force   = $this->option('force');
        $fresh   = $this->option('fresh');

        $query = Tenant::on('central_mysql')->where('is_active', true);
        if ($filter) {
            $query->where('subdomain', $filter);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No matching tenants found.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line("→ [{$tenant->subdomain}] {$tenant->db_name}");

            config(['database.connections.mysql.database' => $tenant->db_name]);
            DB::purge('mysql');
            DB::reconnect('mysql');

            $command = $fresh ? 'migrate:fresh' : 'migrate';
            $args    = ['--database' => 'mysql', '--force' => $force];

            Artisan::call($command, $args, $this->output);
        }

        $this->info("Done — {$tenants->count()} tenant(s) migrated.");
        return self::SUCCESS;
    }
}
