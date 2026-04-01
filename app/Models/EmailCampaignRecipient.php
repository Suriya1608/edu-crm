<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaignRecipient extends Model
{
    protected $fillable = [
        'email_campaign_id', 'email', 'name', 'tracking_token',
        'status', 'sent_at', 'opened_at', 'bounced_at', 'bounce_type', 'error_message',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'bounced_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}
