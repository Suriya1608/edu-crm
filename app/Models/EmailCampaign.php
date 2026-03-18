<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $fillable = [
        'name', 'description', 'template_id', 'template_name', 'template_subject', 'template_body',
        'course_filter', 'scheduled_at', 'sent_at', 'status', 'created_by',
        'recipients_count', 'sent_count', 'failed_count', 'opened_count', 'bounced_count', 'click_count',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'sent_at'          => 'datetime',
        'recipients_count' => 'integer',
        'sent_count'       => 'integer',
        'failed_count'     => 'integer',
        'opened_count'     => 'integer',
        'bounced_count'    => 'integer',
        'click_count'      => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function recipients()
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function getOpenRateAttribute(): float
    {
        if ($this->sent_count === 0) return 0.0;
        return round(($this->opened_count / $this->sent_count) * 100, 1);
    }

    public function getDeliveryRateAttribute(): float
    {
        if ($this->recipients_count === 0) return 0.0;
        return round(($this->sent_count / $this->recipients_count) * 100, 1);
    }

    public function getClickRateAttribute(): float
    {
        if ($this->sent_count === 0) return 0.0;
        return round(($this->click_count / $this->sent_count) * 100, 1);
    }

    public function getBounceRateAttribute(): float
    {
        if ($this->sent_count === 0) return 0.0;
        return round(($this->bounced_count / $this->sent_count) * 100, 1);
    }
}
