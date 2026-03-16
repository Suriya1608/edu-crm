<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramAccount extends Model
{
    protected $fillable = [
        'page_id', 'instagram_user_id', 'name',
        'access_token', 'app_secret', 'verify_token', 'is_active',
    ];

    protected $casts = [
        // access_token uses custom accessor/mutator below (plaintext fallback)
        'app_secret' => 'encrypted',
        'is_active'  => 'boolean',
    ];

    /**
     * Decrypt the access token, falling back to the raw value for tokens
     * that were stored as plaintext before the encrypted cast was added.
     */
    public function getAccessTokenAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return decrypt($value);
        } catch (\Exception) {
            return $value; // plaintext token stored directly in DB
        }
    }

    /**
     * Always encrypt before storing so new tokens are protected.
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    public function conversations()
    {
        return $this->hasMany(InstagramConversation::class);
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
