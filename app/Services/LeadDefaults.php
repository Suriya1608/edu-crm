<?php

namespace App\Services;

use App\Models\Setting;

class LeadDefaults
{
    public const DEFAULT_STATUS_KEY = 'default_lead_status';

    public static function allowedStatuses(): array
    {
        return [
            'new',
            'assigned',
            'contacted',
            'interested',
            'follow_up',
            'not_interested',
            'converted',
        ];
    }

    public static function defaultStatus(): string
    {
        $value = (string) Setting::get(self::DEFAULT_STATUS_KEY, 'new');
        return in_array($value, self::allowedStatuses(), true) ? $value : 'new';
    }
}
