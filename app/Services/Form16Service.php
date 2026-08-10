<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTaxDeclaration;
use App\Models\FinancialYear;
use App\Models\Form16;
use App\Models\PayrollRunEmployee;
use App\Models\TaxRegimeSlab;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a Form 16 PDF for one employee/financial year.
 *
 * This produces Part B (the salary & tax computation certificate an employer is entitled
 * to prepare itself) plus a quarter-wise TDS summary built from payroll data, laid out like
 * Part A. It is NOT a substitute for the signed Part A a deductor downloads from TRACES —
 * that always requires the quarterly 24Q return to have been filed with the department
 * first. The generated PDF says so explicitly (see resources/views/payroll/form16.blade.php).
 *
 * Figures are pulled from the same computation engine payroll already trusts
 * (IncomeTaxCalculationService::project()) rather than recomputed here, so a Form 16 always
 * matches what was actually deducted during the year.
 */
class Form16Service
{
    public function __construct(
        private IncomeTaxCalculationService $taxService,
        private PayslipService $payslipService,
    ) {}

    public function generate(Employee $employee, FinancialYear $financialYear, User $actor): Form16
    {
        $regime = $employee->selectedRegimeFor($financialYear) ?? TaxRegimeSlab::REGIME_OLD;
        $lastMonth = Carbon::parse($financialYear->end_date)->format('Y-m');

        $projection = $this->taxService->project($employee, $financialYear, $lastMonth, $regime);
        $config = $financialYear->configFor($regime);
        $standardDeduction = $config ? (float) $config->standard_deduction : 0.0;

        $sections = $regime === TaxRegimeSlab::REGIME_OLD
            ? $this->sectionBreakup($employee, $financialYear)
            : collect();

        $pdf = Pdf::loadView('payroll.form16', [
            'employee' => $employee,
            'company' => $employee->company,
            'financialYear' => $financialYear,
            'regime' => $regime,
            'projection' => $projection,
            'standardDeduction' => $standardDeduction,
            'chapterViaTotal' => (float) $sections->sum('amount'),
            'sections' => $sections,
            'quarters' => $this->quarterlyTds($employee, $financialYear),
            'pan' => $employee->statutoryDetail?->pan,
            'totalTaxInWords' => $this->payslipService->amountInWords((float) $projection->final_tax),
            'generatedOn' => now(),
        ]);

        $path = "form16/{$employee->id}/{$financialYear->id}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return Form16::updateOrCreate(
            ['employee_id' => $employee->id, 'financial_year_id' => $financialYear->id],
            [
                'regime' => $regime,
                'pdf_path' => $path,
                'generated_at' => now(),
                'generated_by' => $actor->id,
            ]
        );
    }

    /**
     * Verified Chapter VI-A / exemption declarations grouped by tax section, for the
     * old-regime deduction breakup table.
     *
     * @return Collection<int, array{code: string, name: string, amount: float}>
     */
    private function sectionBreakup(Employee $employee, FinancialYear $financialYear): Collection
    {
        return EmployeeTaxDeclaration::query()
            ->where('employee_id', $employee->id)
            ->where('financial_year_id', $financialYear->id)
            ->where('status', EmployeeTaxDeclaration::STATUS_VERIFIED)
            ->with('taxSection')
            ->get()
            ->filter(fn (EmployeeTaxDeclaration $d) => $d->taxSection !== null)
            ->groupBy(fn (EmployeeTaxDeclaration $d) => $d->taxSection->code)
            ->map(fn (Collection $group) => [
                'code' => $group->first()->taxSection->code,
                'name' => $group->first()->taxSection->name,
                'amount' => (float) $group->sum('eligible_amount'),
            ])
            ->values();
    }

    /**
     * Quarter-wise TDS actually deducted through payroll (Apr-Jun / Jul-Sep / Oct-Dec / Jan-Mar),
     * standing in for Part A's quarterly deposit summary.
     *
     * @return array<int, array{label: string, months: array<int, string>, amount: float}>
     */
    private function quarterlyTds(Employee $employee, FinancialYear $financialYear): array
    {
        $months = $this->taxService->financialYearMonths($financialYear);
        $quarterLabels = ['Q1 (Apr-Jun)', 'Q2 (Jul-Sep)', 'Q3 (Oct-Dec)', 'Q4 (Jan-Mar)'];

        $runEmployees = PayrollRunEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('payroll_month', $months))
            ->with([
                'payrollRun:id,payroll_month',
                'lines' => fn ($q) => $q->where('label', IncomeTaxCalculationService::TDS_LABEL),
            ])
            ->get();

        $quarters = [];

        foreach (array_chunk($months, 3) as $index => $quarterMonths) {
            $amount = $runEmployees
                ->filter(fn (PayrollRunEmployee $re) => in_array($re->payrollRun->payroll_month, $quarterMonths, true))
                ->flatMap(fn (PayrollRunEmployee $re) => $re->lines)
                ->sum('amount');

            $quarters[] = [
                'label' => $quarterLabels[$index] ?? 'Q'.($index + 1),
                'months' => $quarterMonths,
                'amount' => round((float) $amount, 2),
            ];
        }

        return $quarters;
    }
}
