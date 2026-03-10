<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'login_at',
        'logout_at',
        'duration_minutes',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'location_area',
        'location_city',
        'location_state',
        'location_country',
    ];
}
