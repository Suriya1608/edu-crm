<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelecallerUnavailability extends Model
{
    protected $table = 'telecaller_unavailability';

    protected $fillable = ['user_id', 'blocked_date', 'reason'];

    protected $casts = [
        'blocked_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
