<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs LeaveType/LeavePolicy/EmployeeLeaveBalance access — HR tier only.
 * Leave Applications are governed separately (open to all, query-scoped).
 */
class LeaveMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function create(User $user): bool
    {
        return $user->can('leave.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('leave.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('leave.manage');
    }
}
