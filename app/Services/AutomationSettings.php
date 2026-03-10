<?php

namespace App\Services;

use App\Models\Setting;

class AutomationSettings
{
    public const LEAD_ASSIGNMENT_ENABLED = 'automation_lead_assignment_enabled';
    public const LEAD_ASSIGN_ACTIVE_ONLY = 'automation_assign_active_managers_only';

    public const FOLLOWUP_REMINDER_ENABLED = 'automation_followup_reminder_enabled';
    public const FOLLOWUP_REMINDER_DAYS_BEFORE = 'automation_followup_reminder_days_before';
    public const FOLLOWUP_OVERDUE_HIGHLIGHT = 'automation_followup_overdue_highlight_enabled';

    public const ESCALATION_ENABLED = 'automation_escalation_enabled';
    public const ESCALATE_MISSED_FOLLOWUPS = 'automation_escalate_missed_followups_enabled';
    public const ESCALATE_RESPONSE_SLA = 'automation_escalate_response_sla_enabled';
    public const RESPONSE_SLA_MINUTES = 'automation_response_sla_minutes';
    public const BUSINESS_HOURS_ENABLED = 'business_hours_enabled';
    public const BUSINESS_START_TIME = 'business_start_time';
    public const BUSINESS_END_TIME = 'business_end_time';
    public const WORKING_DAYS = 'working_days';

    public function leadAssignmentEnabled(): bool
    {
        return $this->bool(self::LEAD_ASSIGNMENT_ENABLED, true);
    }

    public function assignToActiveManagersOnly(): bool
    {
        return $this->bool(self::LEAD_ASSIGN_ACTIVE_ONLY, true);
    }

    public function followupReminderEnabled(): bool
    {
        return $this->bool(self::FOLLOWUP_REMINDER_ENABLED, true);
    }

    public function followupReminderDaysBefore(): int
    {
        return max(0, min(30, $this->int(self::FOLLOWUP_REMINDER_DAYS_BEFORE, 0)));
    }

    public function followupOverdueHighlightEnabled(): bool
    {
        return $this->bool(self::FOLLOWUP_OVERDUE_HIGHLIGHT, true);
    }

    public function escalationEnabled(): bool
    {
        return $this->bool(self::ESCALATION_ENABLED, true);
    }

    public function escalateMissedFollowupsEnabled(): bool
    {
        return $this->bool(self::ESCALATE_MISSED_FOLLOWUPS, true);
    }

    public function escalateResponseSlaEnabled(): bool
    {
        return $this->bool(self::ESCALATE_RESPONSE_SLA, true);
    }

    public function responseSlaMinutes(): int
    {
        return max(5, min(10080, $this->int(self::RESPONSE_SLA_MINUTES, 60)));
    }

    public function businessHoursEnabled(): bool
    {
        return $this->bool(self::BUSINESS_HOURS_ENABLED, true);
    }

    public function businessStartTime(): string
    {
        return (string) \App\Models\Setting::get(self::BUSINESS_START_TIME, '09:00');
    }

    public function businessEndTime(): string
    {
        return (string) \App\Models\Setting::get(self::BUSINESS_END_TIME, '18:00');
    }

    public function workingDays(): array
    {
        $raw = (string) \App\Models\Setting::get(self::WORKING_DAYS, json_encode([1, 2, 3, 4, 5, 6]));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [1, 2, 3, 4, 5, 6];
        }

        return collect($decoded)
            ->map(fn($d) => (int) $d)
            ->filter(fn($d) => $d >= 1 && $d <= 7)
            ->unique()
            ->values()
            ->all();
    }

    public function isOperationalNow(): bool
    {
        $day = (int) now()->isoWeekday();
        if (!in_array($day, $this->workingDays(), true)) {
            return false;
        }

        if (!$this->businessHoursEnabled()) {
            return true;
        }

        $start = $this->businessStartTime();
        $end = $this->businessEndTime();
        $nowTime = now()->format('H:i');

        if (!$start || !$end) {
            return true;
        }

        if ($start <= $end) {
            return $nowTime >= $start && $nowTime <= $end;
        }

        // Overnight shift support.
        return $nowTime >= $start || $nowTime <= $end;
    }

    private function bool(string $key, bool $default): bool
    {
        $value = Setting::get($key, $default ? '1' : '0');
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    private function int(string $key, int $default): int
    {
        return (int) Setting::get($key, (string) $default);
    }
}
