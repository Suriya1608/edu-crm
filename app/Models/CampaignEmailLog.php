<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignEmailLog extends Model
{
    protected $fillable = [
        'campaign_id', 'template_id', 'template_name', 'template_subject',
        'sent_by', 'recipients_count', 'sent_count', 'failed_count',
        'status', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
