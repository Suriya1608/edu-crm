<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'description',
        'meta_data',
        'activity_time'
    ];

    protected $casts = [
        'meta_data' => 'array',
        'activity_time' => 'datetime'
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    // 🔥 ADD THIS (IMPORTANT)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
