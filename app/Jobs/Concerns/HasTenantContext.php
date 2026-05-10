<?php

namespace App\Jobs\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

trait HasTenantContext
{
    public int $tenantId = 0;

    public function initTenantContext(): void
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $this->tenantId = ($tenant instanceof Tenant) ? $tenant->id : 0;
    }

    public function switchTenantConnection(): void
    {
        if (!$this->tenantId) {
            return;
        }

        $tenant = Tenant::on('central_mysql')->findOrFail($this->tenantId);
        config(['database.connections.mysql.database' => $tenant->db_name]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }
}
