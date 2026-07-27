<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs Shift, Holiday, and (non-scoped) Attendance record access — HR/Payroll tier only.
 * Attendance Regularization requests are governed separately (open to all, query-scoped).
 */
class AttendanceMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('attendance.manage');
    }
}
