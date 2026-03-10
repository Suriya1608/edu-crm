<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignActivity extends Model
{
    protected $fillable = [
        'campaign_contact_id',
        'type',
        'description',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function contact()
    {
        return $this->belongsTo(CampaignContact::class, 'campaign_contact_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
