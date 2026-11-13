<?php

namespace App\Policies;

use App\Models\User;

/** Divisions, geography, hierarchy levels and positions. */
class FieldForcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fieldforce.view') || $user->can('fieldforce.manage');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('fieldforce.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('fieldforce.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('fieldforce.manage');
    }

    public function restore(User $user): bool
    {
        return $user->can('fieldforce.manage');
    }

    public function forceDelete(User $user): bool
    {
        return $user->can('fieldforce.manage');
    }
}
