<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class TcnUserAccount extends Model
{
    protected $fillable = [
        'user_id',
        'tcn_username',
        'refresh_token',
        'agent_id',
        'hunt_group_id',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ---------------------------------------------------------------
    // Accessors / Mutators — refresh_token stored encrypted
    // ---------------------------------------------------------------

    public function getRefreshTokenPlainAttribute(): ?string
    {
        if (blank($this->refresh_token)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->refresh_token);
        } catch (DecryptException) {
            // backward compat for previously plain-text values
            return $this->refresh_token;
        }
    }

    public function setRefreshTokenEncrypted(string $plain): void
    {
        $this->refresh_token = Crypt::encryptString($plain);
    }

    // ---------------------------------------------------------------
    // Static helpers
    // ---------------------------------------------------------------

    public static function forUser(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    public static function saveForUser(int $userId, array $data): self
    {
        $account = static::firstOrNew(['user_id' => $userId]);

        if (isset($data['tcn_username'])) {
            $account->tcn_username = $data['tcn_username'];
        }
        if (isset($data['agent_id'])) {
            $account->agent_id = $data['agent_id'];
        }
        if (isset($data['hunt_group_id'])) {
            $account->hunt_group_id = $data['hunt_group_id'];
        }
        if (!empty($data['refresh_token'])) {
            $account->setRefreshTokenEncrypted($data['refresh_token']);
        }

        $account->save();

        return $account;
    }
}
