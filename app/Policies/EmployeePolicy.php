<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employee.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employee.view') || $user->employee_id === $employee->id;
    }

    public function create(User $user): bool
    {
        return $user->can('employee.add');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employee.edit');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employee.delete');
    }

    public function restore(User $user): bool
    {
        return $user->can('employee.delete');
    }

    public function forceDelete(User $user): bool
    {
        return $user->can('employee.delete');
    }
}
