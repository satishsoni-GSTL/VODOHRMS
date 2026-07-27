<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Models\SalaryComponent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryStructureService
{
    /**
     * Assign (or revise) an employee's salary structure. Never overwrites a prior structure —
     * the previous active row is closed off (effective_to) and a new versioned row is inserted.
     *
     * @param  array<int, float>  $earningMonthlyAmounts  [salary_component_id => monthly_amount] for earning-type lines, HR-entered.
     */
    public function assign(
        Employee $employee,
        string $effectiveFrom,
        float $annualCtc,
        array $earningMonthlyAmounts,
        ?int $approvedBy = null,
        ?string $remarks = null,
    ): EmployeeSalaryStructure {
        if ($earningMonthlyAmounts === []) {
            throw ValidationException::withMessages(['lines' => 'At least one earning component is required.']);
        }

        return DB::transaction(function () use ($employee, $effectiveFrom, $annualCtc, $earningMonthlyAmounts, $approvedBy, $remarks) {
            $previous = $employee->currentSalaryStructure();

            if ($previous && Carbon::parse($effectiveFrom)->lte(Carbon::parse($previous->effective_from))) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Effective date must be after the current structure\'s effective date ('.$previous->effective_from->toDateString().').',
                ]);
            }

            $structure = EmployeeSalaryStructure::create([
                'employee_id' => $employee->id,
                'effective_from' => $effectiveFrom,
                'annual_ctc' => $annualCtc,
                'monthly_gross' => 0,
                'previous_ctc' => $previous?->annual_ctc,
                'revised_ctc' => $annualCtc,
                'increment_percent' => $previous && (float) $previous->annual_ctc > 0
                    ? round((($annualCtc - $previous->annual_ctc) / $previous->annual_ctc) * 100, 2)
                    : null,
                'approved_by' => $approvedBy,
                'remarks' => $remarks,
                'is_active' => true,
            ]);

            $earningComponents = SalaryComponent::whereIn('id', array_keys($earningMonthlyAmounts))->get()->keyBy('id');
            $basicMonthly = 0.0;

            foreach ($earningMonthlyAmounts as $componentId => $monthlyAmount) {
                $structure->lines()->create([
                    'salary_component_id' => $componentId,
                    'monthly_amount' => $monthlyAmount,
                    'annual_amount' => round($monthlyAmount * 12, 2),
                ]);

                if (strtoupper($earningComponents->get($componentId)?->code ?? '') === 'BASIC') {
                    $basicMonthly = (float) $monthlyAmount;
                }
            }

            $this->applyAutoComputedComponents($structure, $basicMonthly);

            $structure->monthly_gross = $structure->lines()
                ->whereHas('component', fn ($q) => $q->where('is_gross_component', true))
                ->sum('monthly_amount');
            $structure->save();

            if ($previous) {
                $previous->update([
                    'effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
                    'is_active' => false,
                ]);
            }

            return $structure->fresh('lines.component');
        });
    }

    /**
     * Auto-compute deduction / employer_contribution components (PF, ESIC, Professional Tax, etc.)
     * that are configured on the salary_components master rather than entered per-employee.
     */
    private function applyAutoComputedComponents(EmployeeSalaryStructure $structure, float $basicMonthly): void
    {
        $autoComponents = SalaryComponent::query()
            ->whereIn('type', [SalaryComponent::TYPE_DEDUCTION, SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION])
            ->active()
            ->get();

        foreach ($autoComponents as $component) {
            $amount = match ($component->calculation_type) {
                SalaryComponent::CALC_PERCENTAGE => round($basicMonthly * (float) ($component->default_percentage ?? 0) / 100, 2),
                SalaryComponent::CALC_FIXED => (float) ($component->default_amount ?? 0),
                default => 0.0,
            };

            if ($amount <= 0) {
                continue;
            }

            $structure->lines()->create([
                'salary_component_id' => $component->id,
                'monthly_amount' => $amount,
                'annual_amount' => round($amount * 12, 2),
            ]);
        }
    }
}
