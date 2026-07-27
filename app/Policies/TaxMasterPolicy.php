<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs FinancialYear / TaxSection / TaxRegimeSlab / TaxRegimeConfig access — HR/Payroll tier only.
 * Employee tax regime selection and declarations are governed separately (open to all, query-scoped).
 */
class TaxMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax.view');
    }

    public function view(User $user): bool
    {
        return $user->can('tax.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tax.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('tax.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('tax.manage');
    }
}
