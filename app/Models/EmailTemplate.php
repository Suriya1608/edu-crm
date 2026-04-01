<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name', 'subject', 'body', 'blocks_json', 'template_type', 'status', 'created_by',
    ];

    protected $casts = [
        'blocks_json' => 'array',
    ];

    // ── Encrypted Route Binding ───────────────────────────────────────────────

    public function getRouteKey(): string
    {
        return Crypt::encryptString((string) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        try {
            $id = Crypt::decryptString($value);
        } catch (DecryptException) {
            abort(404);
        }

        return $this->where('id', $id)->firstOrFail();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailLogs()
    {
        return $this->hasMany(CampaignEmailLog::class, 'template_id');
    }

    public static function active()
    {
        return static::where('status', 'active')->orderBy('name')->get();
    }
}
