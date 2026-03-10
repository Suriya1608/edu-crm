<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'provider',
        'call_sid',
        'customer_number',
        'direction',
        'status',
        'answered_at',
        'ended_at',
        'ended_by',
        'end_reason',
        'duration',
        'recording_url',
        'outcome',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'ended_at'    => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
