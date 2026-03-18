<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailClick extends Model
{
    protected $fillable = [
        'email_campaign_id', 'recipient_id', 'tracking_token',
        'url', 'clicked_at', 'ip_address', 'click_count',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'click_count' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function recipient()
    {
        return $this->belongsTo(EmailCampaignRecipient::class, 'recipient_id');
    }
}
