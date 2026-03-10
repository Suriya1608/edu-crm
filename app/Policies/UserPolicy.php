<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for User management.
 * All user-management actions are restricted to admin role only.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, User $target): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, User $target): bool
    {
        return $user->role === 'admin' && $user->id !== $target->id;
    }

    public function toggleStatus(User $user, User $target): bool
    {
        return $user->role === 'admin' && $user->id !== $target->id;
    }

    public function forceLogout(User $user, User $target): bool
    {
        return $user->role === 'admin' && $user->id !== $target->id;
    }
}
