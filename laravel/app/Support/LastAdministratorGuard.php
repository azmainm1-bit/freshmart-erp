<?php

namespace App\Support;

use App\Models\User;

/**
 * Prevents the last active administrator from being deactivated, demoted,
 * or deleted — docs/ARCHITECTURE.md "Prevent accidental removal of the last
 * active administrator." Call before persisting any change that could
 * remove admin access from a user.
 */
class LastAdministratorGuard
{
    public static function wouldRemoveLastAdmin(User $user, bool $willBeActive, bool $willHaveAdminRole): bool
    {
        if (! $user->hasRole('admin')) {
            return false; // this user isn't an admin today; can't be "the last one"
        }

        if ($willBeActive && $willHaveAdminRole) {
            return false; // still an active admin after the change
        }

        $otherActiveAdmins = User::role('admin')
            ->where('active', true)
            ->where('id', '!=', $user->id)
            ->exists();

        return ! $otherActiveAdmins;
    }
}
