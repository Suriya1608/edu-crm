<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for system settings.
 * Only admin users may view or modify any system settings.
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user): bool
    {
        return $user->role === 'admin';
    }
}
