<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Authorization policy for Lead resources.
 *
 * Role rules:
 *  admin      — full access to all leads
 *  manager    — access leads assigned_by = this manager, or leads with no assigned_by
 *  telecaller — access only leads assigned_to = this telecaller
 */
class LeadPolicy
{
    /** Any authenticated user may list leads (scope enforced in controller) */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'manager', 'telecaller'], true);
    }

    /** View a specific lead */
    public function view(User $user, Lead $lead): bool
    {
        return match ($user->role) {
            'admin'      => true,
            'manager'    => (int) $lead->assigned_by === $user->id,
            'telecaller' => (int) $lead->assigned_to === $user->id,
            default      => false,
        };
    }

    /** Only admin and manager may create leads */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'manager'], true);
    }

    /** Only admin and manager may update lead details */
    public function update(User $user, Lead $lead): bool
    {
        return match ($user->role) {
            'admin'   => true,
            'manager' => (int) $lead->assigned_by === $user->id,
            default   => false,
        };
    }

    /** Status change: telecallers may update their own leads' status */
    public function changeStatus(User $user, Lead $lead): bool
    {
        return match ($user->role) {
            'admin'      => true,
            'manager'    => (int) $lead->assigned_by === $user->id,
            'telecaller' => (int) $lead->assigned_to === $user->id,
            default      => false,
        };
    }

    /** Assign/reassign lead to another user */
    public function assign(User $user, Lead $lead): bool
    {
        return match ($user->role) {
            'admin'   => true,
            'manager' => (int) $lead->assigned_by === $user->id,
            default   => false,
        };
    }

    /** Merge leads — admin and manager only */
    public function merge(User $user, Lead $lead): bool
    {
        return in_array($user->role, ['admin', 'manager'], true);
    }

    /** Delete — admin only */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->role === 'admin';
    }
}
