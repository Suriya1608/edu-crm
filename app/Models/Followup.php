<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Followup extends Model
{
    protected $table = 'followups';

    protected $fillable = [
        'lead_id',
        'user_id',
        'remarks',
        'next_followup',
        'followup_time',
        'completed_at',
        'escalated_at',
        'reminder_notified_at',
    ];

    protected $casts = [
        'next_followup' => 'date',
        'completed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'reminder_notified_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(\App\Models\Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
