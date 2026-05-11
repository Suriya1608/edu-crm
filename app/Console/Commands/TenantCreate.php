<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
                            {name       : Display name of the client}
                            {subdomain  : Subdomain slug (e.g. client1)}
                            {--db=      : Custom DB name (default: crm_{subdomain})}
                            {--existing : Skip DB creation and migrations — register an existing DB}
                            {--admin-email= : Create an admin user with this email}
                            {--admin-password= : Password for the admin user}';

    protected $description = 'Create a new tenant: registers in central DB, creates tenant DB, and runs migrations';

    public function handle(): int
    {
        $name      = $this->argument('name');
        $subdomain = strtolower(trim($this->argument('subdomain')));
        $dbName    = $this->option('db') ?: 'crm_' . preg_replace('/[^a-z0-9_]/', '_', $subdomain);

        // Validate subdomain
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,48}[a-z0-9]$|^[a-z0-9]$/', $subdomain)) {
            $this->error('Subdomain must be lowercase alphanumeric (hyphens allowed, 1-50 chars).');
            return self::FAILURE;
        }

        if (Tenant::on('central_mysql')->where('subdomain', $subdomain)->exists()) {
            $this->error("Tenant with subdomain '{$subdomain}' already exists.");
            return self::FAILURE;
        }

        if (!$this->option('existing')) {
            $this->info("Creating database: {$dbName}");
            DB::connection('central_mysql')
                ->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $tenant = Tenant::on('central_mysql')->create([
            'name'      => $name,
            'subdomain' => $subdomain,
            'db_name'   => $dbName,
            'is_active' => true,
        ]);

        $this->info("Tenant registered in central DB (id: {$tenant->id}).");

        if (!$this->option('existing')) {
            // Switch mysql connection to the new tenant DB and run migrations
            config(['database.connections.mysql.database' => $dbName]);
            DB::purge('mysql');
            DB::reconnect('mysql');

            $this->info("Running migrations on {$dbName}...");
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--force'    => true,
            ], $this->output);
        }

        // Optionally create an admin user in the tenant DB
        if ($email = $this->option('admin-email')) {
            $password = $this->option('admin-password') ?: 'changeme123';

            config(['database.connections.mysql.database' => $dbName]);
            DB::purge('mysql');
            DB::reconnect('mysql');

            DB::table('users')->insert([
                'name'       => 'Admin',
                'email'      => $email,
                'password'   => Hash::make($password),
                'role'       => 'admin',
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("Admin user created: {$email}");
        }

        // Create per-tenant storage folders
        $storagePath = storage_path("app/public/tenants/{$subdomain}");
        if (!is_dir($storagePath)) {
            mkdir($storagePath . '/uploads', 0755, true);
            mkdir($storagePath . '/logos',   0755, true);
            $this->info("Storage folder created: storage/app/public/tenants/{$subdomain}");
        }

        $appDomain = env('APP_DOMAIN', 'insighttechnology.in');
        $this->newLine();
        $this->info("✓ Tenant '{$name}' is ready.");
        $this->line("  Subdomain : https://{$subdomain}.{$appDomain}");
        $this->line("  Database  : {$dbName}");
        $this->newLine();

        return self::SUCCESS;
    }
}
