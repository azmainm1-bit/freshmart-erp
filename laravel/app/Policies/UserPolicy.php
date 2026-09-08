<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage');
    }

    /**
     * Deactivating and role changes both go through this same check — the
     * last-active-admin protection (docs/ARCHITECTURE.md) applies to both.
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $actor->can('users.manage');
    }
}
