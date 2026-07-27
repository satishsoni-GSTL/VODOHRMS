<?php

namespace App\Policies;

use App\Models\PolicyDocument;
use App\Models\User;

class PolicyDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PolicyDocument $policyDocument): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('policy.manage');
    }

    public function update(User $user, PolicyDocument $policyDocument): bool
    {
        return $user->can('policy.manage');
    }

    public function delete(User $user, PolicyDocument $policyDocument): bool
    {
        return $user->can('policy.manage');
    }

    public function restore(User $user): bool
    {
        return $user->can('policy.manage');
    }

    public function forceDelete(User $user): bool
    {
        return $user->can('policy.manage');
    }
}
