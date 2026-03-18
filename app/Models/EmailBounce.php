<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailBounce extends Model
{
    protected $fillable = [
        'email', 'campaign_id', 'recipient_id',
        'bounce_type', 'reason', 'provider',
    ];

    public function campaign()
    {
        return $this->belongsTo(EmailCampaign::class, 'campaign_id');
    }

    public function recipient()
    {
        return $this->belongsTo(EmailCampaignRecipient::class, 'recipient_id');
    }

    /**
     * Check if a given email has any hard bounces (should be suppressed).
     */
    public static function isHardBounced(string $email): bool
    {
        return static::where('email', $email)
            ->where('bounce_type', 'hard')
            ->exists();
    }
}
