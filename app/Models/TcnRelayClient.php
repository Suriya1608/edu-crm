<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcnRelayClient extends Model
{
    protected $fillable = ['name', 'domain', 'is_active', 'notes', 'last_relayed_at'];

    protected $casts = [
        'is_active'       => 'boolean',
        'last_relayed_at' => 'datetime',
    ];

    public static function findByDomain(string $domain): ?self
    {
        return static::where('domain', rtrim($domain, '/'))->first();
    }
}
