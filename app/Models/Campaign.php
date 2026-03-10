<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
    ];

    public function contacts()
    {
        return $this->hasMany(CampaignContact::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalContactsAttribute(): int
    {
        return $this->contacts()->count();
    }

    public function getContactedCountAttribute(): int
    {
        return $this->contacts()->where('status', '!=', 'pending')->count();
    }

    public function getConvertedCountAttribute(): int
    {
        return $this->contacts()->where('status', 'converted')->count();
    }
}
