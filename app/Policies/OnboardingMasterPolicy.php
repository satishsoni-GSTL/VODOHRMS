<?php

namespace App\Policies;

use App\Models\User;

class OnboardingMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('onboarding.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('onboarding.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('onboarding.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('onboarding.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('onboarding.manage');
    }
}
