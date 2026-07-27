<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs SalaryComponent / EmployeeSalaryStructure / PayrollInput access — Payroll/HR tier only.
 */
class PayrollMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('payroll.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('payroll.manage');
    }
}
