<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs ExpenseCategory access — HR/Finance tier only.
 * Expense Claims are governed separately (open to all, query-scoped).
 */
class ExpenseMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expense.view');
    }

    public function view(User $user): bool
    {
        return $user->can('expense.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expense.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('expense.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('expense.manage');
    }
}
