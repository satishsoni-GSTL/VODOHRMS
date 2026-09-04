<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTaxDeclaration;
use App\Models\EmployeeTaxProjection;
use App\Models\FinancialYear;
use App\Models\FullFinalSettlement;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollRunEmployeeLine;
use App\Models\TaxRegimeConfig;
use App\Models\TaxRegimeSlab;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class IncomeTaxCalculationService
{
    public const TDS_LABEL = 'Income Tax (TDS)';

    /**
     * @return string[] Every 'Y-m' month string within the financial year, in order.
     */
    public function financialYearMonths(FinancialYear $financialYear): array
    {
        $months = [];
        $cursor = Carbon::parse($financialYear->start_date)->startOfMonth();
        $end = Carbon::parse($financialYear->end_date)->startOfMonth();

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @param  Collection<int, TaxRegimeSlab>  $slabs
     */
    public function calculateSlabTax(Collection $slabs, float $taxableIncome): float
    {
        $tax = 0.0;

        foreach ($slabs as $slab) {
            $from = (float) $slab->income_from;
            $to = $slab->income_to !== null ? (float) $slab->income_to : PHP_FLOAT_MAX;

            if ($taxableIncome <= $from) {
                continue;
            }

            $amountInSlab = min($taxableIncome, $to) - $from;
            $tax += $amountInSlab * (float) $slab->tax_percent / 100;
        }

        return round($tax, 2);
    }

    public function calculateRebate(?TaxRegimeConfig $config, float $taxableIncome, float $taxBeforeRebate): float
    {
        if (! $config || $config->rebate_limit_income === null) {
            return 0.0;
        }

        if ($taxableIncome > (float) $config->rebate_limit_income) {
            return 0.0;
        }

        $maxRebate = $config->rebate_max_amount !== null ? (float) $config->rebate_max_amount : $taxBeforeRebate;

        return round(min($taxBeforeRebate, $maxRebate), 2);
    }

    public function calculateSurcharge(?TaxRegimeConfig $config, float $taxableIncome, float $taxAfterRebate): float
    {
        $rules = $config?->surcharge_rules ?? [];
        $percent = 0.0;

        foreach ($rules as $rule) {
            if ($taxableIncome > (float) ($rule['above_income'] ?? PHP_FLOAT_MAX)) {
                $percent = max($percent, (float) ($rule['percent'] ?? 0));
            }
        }

        return round($taxAfterRebate * $percent / 100, 2);
    }

    public function computeExemptions(Employee $employee, FinancialYear $financialYear, string $regime): float
    {
        $config = $financialYear->configFor($regime);
        $standardDeduction = $config ? (float) $config->standard_deduction : 0.0;

        if ($regime === TaxRegimeSlab::REGIME_NEW) {
            return $standardDeduction;
        }

        $declaredSum = (float) EmployeeTaxDeclaration::query()
            ->where('employee_id', $employee->id)
            ->where('financial_year_id', $financialYear->id)
            ->where('status', EmployeeTaxDeclaration::STATUS_VERIFIED)
            ->sum('eligible_amount');

        return $standardDeduction + $declaredSum;
    }

    public function projectAnnualIncome(Employee $employee, FinancialYear $financialYear, string $payrollMonth): float
    {
        $fyMonths = $this->financialYearMonths($financialYear);

        $paidSoFar = (float) PayrollRunEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', function ($q) use ($fyMonths, $payrollMonth) {
                $q->whereIn('payroll_month', $fyMonths)->where('payroll_month', '<=', $payrollMonth);
            })
            ->sum('gross_earnings');

        $remainingMonths = count(array_filter($fyMonths, fn ($m) => $m > $payrollMonth));
        $currentMonthlyGross = (float) ($employee->currentSalaryStructure()?->monthly_gross ?? 0);

        return round($paidSoFar + $this->fnfTaxableEarnings($employee, $financialYear) + ($remainingMonths * $currentMonthlyGross), 2);
    }

    /**
     * Taxable salary paid through an employee's Full & Final settlement instead of a normal
     * payroll run — pending (part-month/notice-period) salary, bonus, and other earnings.
     * These never appear in payroll_run_employees, so without this a resigned employee's
     * Form 16 / tax projection would silently exclude everything they were actually paid at
     * exit and reflect only what was formally run through monthly payroll ("based on
     * credit") rather than what they actually earned ("based on salary").
     *
     * Only PAID settlements count — draft/calculated/approved figures can still change.
     * Deliberately excludes `leave_encashment` and `reimbursement`: leave encashment has its
     * own partial tax-exemption rules (Section 10(10AA)) this codebase doesn't model, and
     * reimbursement is an expense repayment, not income — including either here without that
     * exemption logic would overstate tax. Known simplification; see docs/ARCHITECTURE.md.
     */
    private function fnfTaxableEarnings(Employee $employee, FinancialYear $financialYear): float
    {
        return (float) FullFinalSettlement::query()
            ->where('employee_id', $employee->id)
            ->where('status', FullFinalSettlement::STATUS_PAID)
            ->whereHas('resignation', fn ($q) => $q
                ->whereNotNull('approved_last_working_date')
                ->whereBetween('approved_last_working_date', [$financialYear->start_date, $financialYear->end_date]))
            ->get()
            ->sum(fn (FullFinalSettlement $settlement) => (float) $settlement->pending_salary
                + (float) $settlement->bonus_incentive
                + (float) $settlement->other_earnings);
    }

    private function tdsDeductedTillDate(Employee $employee, FinancialYear $financialYear, string $payrollMonth): float
    {
        $fyMonths = $this->financialYearMonths($financialYear);

        // Only TDS from runs that were actually finalized/locked counts as "already
        // deducted". A draft/calculated (or reopened) run is provisional — its TDS line
        // has not hit a payslip, so treating it as deducted would understate the tax
        // still to withhold and show phantom deductions in the tax comparison.
        $runEmployeeIds = PayrollRunEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', function ($q) use ($fyMonths, $payrollMonth) {
                $q->whereIn('payroll_month', $fyMonths)
                    ->where('payroll_month', '<=', $payrollMonth)
                    ->whereIn('status', [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED]);
            })
            ->pluck('id');

        $payrollTds = (float) PayrollRunEmployeeLine::query()
            ->whereIn('payroll_run_employee_id', $runEmployeeIds)
            ->where('label', self::TDS_LABEL)
            ->sum('amount');

        return $payrollTds + $this->fnfTdsDeducted($employee, $financialYear);
    }

    /**
     * TDS entered on a PAID Full & Final settlement — mirrors fnfTaxableEarnings() above so
     * the income added there and the tax already withheld against it both count together.
     */
    private function fnfTdsDeducted(Employee $employee, FinancialYear $financialYear): float
    {
        return (float) FullFinalSettlement::query()
            ->where('employee_id', $employee->id)
            ->where('status', FullFinalSettlement::STATUS_PAID)
            ->whereHas('resignation', fn ($q) => $q
                ->whereNotNull('approved_last_working_date')
                ->whereBetween('approved_last_working_date', [$financialYear->start_date, $financialYear->end_date]))
            ->sum('tds');
    }

    public function project(Employee $employee, FinancialYear $financialYear, string $payrollMonth, string $regime): EmployeeTaxProjection
    {
        $projectedIncome = $this->projectAnnualIncome($employee, $financialYear, $payrollMonth);
        $exemptions = $this->computeExemptions($employee, $financialYear, $regime);
        $taxableIncome = max(0, $projectedIncome - $exemptions);

        $slabs = $financialYear->slabsFor($regime);
        $config = $financialYear->configFor($regime);

        $taxBeforeRebate = $this->calculateSlabTax($slabs, $taxableIncome);
        $rebate = $this->calculateRebate($config, $taxableIncome, $taxBeforeRebate);
        $taxAfterRebate = $taxBeforeRebate - $rebate;
        $surcharge = $this->calculateSurcharge($config, $taxableIncome, $taxAfterRebate);
        $cessPercent = $config ? (float) $config->cess_percent : 0.0;
        $cess = round(($taxAfterRebate + $surcharge) * $cessPercent / 100, 2);
        $finalTax = round($taxAfterRebate + $surcharge + $cess, 2);

        $tdsDeducted = $this->tdsDeductedTillDate($employee, $financialYear, $payrollMonth);
        $remainingTax = max(0, round($finalTax - $tdsDeducted, 2));

        $fyMonths = $this->financialYearMonths($financialYear);
        $remainingMonthsCount = max(1, count(array_filter($fyMonths, fn ($m) => $m >= $payrollMonth)));
        $projectedMonthlyTds = round($remainingTax / $remainingMonthsCount, 2);

        return EmployeeTaxProjection::updateOrCreate(
            ['employee_id' => $employee->id, 'financial_year_id' => $financialYear->id, 'regime' => $regime],
            [
                'projected_annual_income' => $projectedIncome,
                'total_exemptions' => $exemptions,
                'taxable_income' => $taxableIncome,
                'tax_before_rebate' => $taxBeforeRebate,
                'rebate' => $rebate,
                'surcharge' => $surcharge,
                'cess' => $cess,
                'final_tax' => $finalTax,
                'tds_deducted_till_date' => $tdsDeducted,
                'remaining_tax' => $remainingTax,
                'projected_monthly_tds' => $projectedMonthlyTds,
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * @return array{old: EmployeeTaxProjection, new: EmployeeTaxProjection, recommended: string}
     */
    public function compareRegimes(Employee $employee, FinancialYear $financialYear, string $payrollMonth): array
    {
        $old = $this->project($employee, $financialYear, $payrollMonth, TaxRegimeSlab::REGIME_OLD);
        $new = $this->project($employee, $financialYear, $payrollMonth, TaxRegimeSlab::REGIME_NEW);

        return [
            'old' => $old,
            'new' => $new,
            'recommended' => $old->final_tax <= $new->final_tax ? TaxRegimeSlab::REGIME_OLD : TaxRegimeSlab::REGIME_NEW,
        ];
    }

    /**
     * The monthly TDS to deduct in payroll for this employee/month, recalculated fresh
     * against their currently selected regime (defaults to old regime if none selected).
     */
    public function monthlyTdsForPayroll(Employee $employee, FinancialYear $financialYear, string $payrollMonth): float
    {
        $regime = $employee->selectedRegimeFor($financialYear) ?? TaxRegimeSlab::REGIME_OLD;
        $projection = $this->project($employee, $financialYear, $payrollMonth, $regime);

        return (float) $projection->projected_monthly_tds;
    }

    public function financialYearForMonth(string $payrollMonth): ?FinancialYear
    {
        $date = Carbon::createFromFormat('Y-m', $payrollMonth)->startOfMonth();

        return FinancialYear::query()
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();
    }
}
