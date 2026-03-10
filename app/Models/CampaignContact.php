<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignContact extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'phone',
        'email',
        'course',
        'city',
        'status',
        'assigned_to',
        'next_followup',
        'followup_time',
        'call_count',
    ];

    protected $casts = [
        'next_followup' => 'date',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(CampaignActivity::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'        => 'Pending',
            'called'         => 'Called',
            'interested'     => 'Interested',
            'not_interested' => 'Not Interested',
            'no_answer'      => 'No Answer',
            'callback'       => 'Callback',
            'converted'      => 'Converted',
            default          => ucfirst($status),
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending'        => 'secondary',
            'called'         => 'info',
            'interested'     => 'success',
            'not_interested' => 'danger',
            'no_answer'      => 'warning',
            'callback'       => 'primary',
            'converted'      => 'success',
            default          => 'secondary',
        };
    }
}
