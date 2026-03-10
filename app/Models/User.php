<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'status',
        'is_online',
        'last_seen_at',
        'password',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'is_online'             => 'boolean',
            'last_seen_at'          => 'datetime',
            'locked_until'          => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');
        $limit = (int) Setting::get('login_attempt_limit', 5);
        if ($this->fresh()->failed_login_attempts >= $limit) {
            $this->update(['locked_until' => now()->addHours(24)]);
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update(['failed_login_attempts' => 0, 'locked_until' => null]);
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }
}
