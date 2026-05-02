<?php

namespace App\Services;

use App\Models\CourseManagerAssignment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadAssignmentService
{
    private const TC_RR_KEY = 'lead_rr_last_telecaller_id';

    public function __construct(
        private ManagerLeadAllocator $managerAllocator,
        private AutomationSettings   $settings,
    ) {}

    /**
     * Assign a freshly created incoming lead (API / landing page capture).
     * Sets assigned_by and manager_assigned_at based on the active mode.
     * Open Pool leaves both null — managers claim from the pool.
     */
    public function assignIncomingLead(Lead $lead): void
    {
        $mode = $this->settings->leadAssignmentMode();

        if ($mode === 'open_pool') {
            return;
        }

        $managerId = match ($mode) {
            'course_based' => $this->resolveManagerByCourse($lead) ?? $this->managerAllocator->resolveManagerIdForIncomingLead(),
            default        => $this->managerAllocator->resolveManagerIdForIncomingLead(),
        };

        if ($managerId) {
            $lead->assigned_by        = $managerId;
            $lead->manager_assigned_at = now();
            $lead->saveQuietly();
        }
    }

    /**
     * Manager claims a lead from the open pool.
     */
    public function claimLead(Lead $lead, int $managerId): void
    {
        $lead->assigned_by         = $managerId;
        $lead->manager_assigned_at = now();
        $lead->save();
    }

    /**
     * Returns the next telecaller ID using system-wide round-robin.
     */
    public function roundRobinTelecaller(): ?int
    {
        $ids = User::where('role', 'telecaller')
            ->where('status', 1)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        if ($ids->count() === 1) {
            return (int) $ids->first();
        }

        return DB::transaction(function () use ($ids) {
            $row    = DB::table('settings')->where('key', self::TC_RR_KEY)->lockForUpdate()->first();
            $lastId = $row ? (int) $row->value : 0;

            $nextId = $ids->first(fn($id) => $id > $lastId) ?? (int) $ids->first();

            DB::table('settings')->updateOrInsert(
                ['key' => self::TC_RR_KEY],
                ['value' => (string) $nextId, 'updated_at' => now(), 'created_at' => now()]
            );

            return $nextId;
        });
    }

    private function resolveManagerByCourse(Lead $lead): ?int
    {
        if (!$lead->course_id) {
            return null;
        }

        return CourseManagerAssignment::where('course_id', $lead->course_id)
            ->where('is_active', true)
            ->value('manager_id');
    }
}
