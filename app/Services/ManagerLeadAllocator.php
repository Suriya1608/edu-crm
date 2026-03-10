<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManagerLeadAllocator
{
    private const SETTING_KEY = 'lead_rr_last_manager_id';

    public function __construct(private AutomationSettings $automationSettings)
    {
    }

    public function resolveManagerIdForIncomingLead(): ?int
    {
        if (!$this->automationSettings->leadAssignmentEnabled()) {
            return null;
        }

        $managerQuery = User::where('role', 'manager');
        if ($this->automationSettings->assignToActiveManagersOnly()) {
            $managerQuery->where('status', 1);
        }

        $activeManagerIds = $managerQuery
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values();

        if ($activeManagerIds->isEmpty()) {
            return null;
        }

        if ($activeManagerIds->count() === 1) {
            $managerId = (int) $activeManagerIds->first();
            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_KEY],
                ['value' => (string) $managerId, 'updated_at' => now(), 'created_at' => now()]
            );
            return $managerId;
        }

        return DB::transaction(function () use ($activeManagerIds) {
            $settingRow = DB::table('settings')
                ->where('key', self::SETTING_KEY)
                ->lockForUpdate()
                ->first();

            $lastManagerId = $settingRow ? (int) $settingRow->value : 0;

            $nextManagerId = $activeManagerIds
                ->first(fn($id) => $id > $lastManagerId);

            if (!$nextManagerId) {
                $nextManagerId = (int) $activeManagerIds->first();
            }

            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_KEY],
                ['value' => (string) $nextManagerId, 'updated_at' => now(), 'created_at' => now()]
            );

            return (int) $nextManagerId;
        });
    }
}
