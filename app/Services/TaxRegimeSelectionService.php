<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTaxRegime;
use App\Models\FinancialYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Owns the employee_tax_regimes append-only history (same pattern as salary structures).
 *
 * Two independent things can lock a selection — either is sufficient:
 *  - The row's own `lock_date` has arrived: HR/Payroll explicitly "fixed" this employee's
 *    regime via the admin panel's Lock action (see EmployeeTaxRegimeResource).
 *  - TaxRegimeConfig.regime_change_allowed is false for that financial year/regime: a
 *    blanket freeze HR can flip for everyone at once (e.g. once the declaration window
 *    closes), independent of any per-employee lock.
 * Users holding `tax.manage` (HR/Payroll admins) always bypass both, so they can "fix" an
 * employee's regime regardless of state — everyone else is blocked once locked and must ask
 * HR to change or unlock it.
 */
class TaxRegimeSelectionService
{
    public function latestFor(Employee $employee, FinancialYear $financialYear): ?EmployeeTaxRegime
    {
        return EmployeeTaxRegime::query()
            ->where('employee_id', $employee->id)
            ->where('financial_year_id', $financialYear->id)
            ->orderByDesc('selection_date')
            ->orderByDesc('id')
            ->first();
    }

    public function isLocked(EmployeeTaxRegime $regime, ?FinancialYear $financialYear = null): bool
    {
        $rowLocked = $regime->lock_date && ! Carbon::parse($regime->lock_date)->isFuture();

        if ($rowLocked) {
            return true;
        }

        $financialYear ??= $regime->financialYear;

        return $financialYear?->configFor($regime->selected_regime)?->regime_change_allowed === false;
    }

    /**
     * Create a new regime selection row for the employee, enforcing the lock for anyone
     * without `tax.manage`. Locking itself is a separate, explicit action (see lock()) —
     * a plain select() never locks the row it creates.
     *
     * @throws ValidationException if the employee's current selection is locked and $actor cannot override it.
     */
    public function select(Employee $employee, FinancialYear $financialYear, string $regime, User $actor): EmployeeTaxRegime
    {
        $latest = $this->latestFor($employee, $financialYear);

        if ($latest && ! $actor->can('tax.manage') && $this->isLocked($latest, $financialYear)) {
            throw ValidationException::withMessages([
                'selected_regime' => "Your tax regime for {$financialYear->name} has been locked by HR/Payroll and can no longer be changed here. Contact HR if this needs correction.",
            ]);
        }

        return EmployeeTaxRegime::create([
            'employee_id' => $employee->id,
            'financial_year_id' => $financialYear->id,
            'selected_regime' => $regime,
            'selection_date' => now()->toDateString(),
            'lock_date' => null,
            'changed_by' => $actor->id,
        ]);
    }

    /**
     * Admin-only: freeze this selection so the employee can no longer self-change it
     * (unless the FY's change window is reopened). This is "fixing" the regime.
     */
    public function lock(EmployeeTaxRegime $regime): EmployeeTaxRegime
    {
        $regime->update(['lock_date' => now()->toDateString()]);

        return $regime;
    }

    public function unlock(EmployeeTaxRegime $regime): EmployeeTaxRegime
    {
        $regime->update(['lock_date' => null]);

        return $regime;
    }
}
