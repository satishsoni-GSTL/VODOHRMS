<?php

namespace App\Exports\Reports;

use App\Models\PayrollRunEmployee;
use App\Models\PayrollRunEmployeeLine;
use App\Models\SalaryComponent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Payroll sheet: one row per employee, one column per salary component that
 * appears in the run(s) — earnings, then Gross, then deductions, then
 * Total Deductions and Net Pay (in-hand), then employer contributions.
 */
class PayrollReportExport implements FromCollection, WithHeadings
{
    private ?Collection $rows = null;

    /** @var array<string, array<int, string>> component_type => ordered labels */
    private array $columns = [];

    public function __construct(private readonly string $month, private readonly ?int $payrollRunId = null) {}

    public function headings(): array
    {
        $this->load();

        return [
            'Employee Code', 'Name', 'Paid Days', 'LOP Days', 'LOP Amount',
            ...$this->columns[SalaryComponent::TYPE_EARNING],
            'Gross Salary',
            ...$this->columns[SalaryComponent::TYPE_DEDUCTION],
            'Total Deductions',
            'In-hand Salary (Net Pay)',
            ...$this->columns[SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION],
            'Total Employer Contributions',
            'Status',
        ];
    }

    public function collection()
    {
        $this->load();

        return $this->rows->map(function (PayrollRunEmployee $runEmployee) {
            $amounts = $runEmployee->lines
                ->groupBy(fn (PayrollRunEmployeeLine $line) => $line->component_type.'|'.$line->label)
                ->map(fn (Collection $lines) => round($lines->sum('amount'), 2));

            $cells = fn (string $type) => array_map(
                fn (string $label) => $amounts->get($type.'|'.$label, 0),
                $this->columns[$type],
            );

            return [
                $runEmployee->employee?->employee_code,
                $runEmployee->employee?->full_name,
                $runEmployee->paid_days,
                $runEmployee->lop_days,
                $runEmployee->lop_amount,
                ...$cells(SalaryComponent::TYPE_EARNING),
                $runEmployee->gross_earnings,
                ...$cells(SalaryComponent::TYPE_DEDUCTION),
                $runEmployee->total_deductions,
                $runEmployee->net_pay,
                ...$cells(SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION),
                $runEmployee->employer_contributions,
                ucfirst($runEmployee->status),
            ];
        });
    }

    private function load(): void
    {
        if ($this->rows !== null) {
            return;
        }

        $this->rows = PayrollRunEmployee::query()
            ->with(['employee', 'lines.component'])
            ->when(
                $this->payrollRunId,
                fn ($q) => $q->where('payroll_run_id', $this->payrollRunId),
                fn ($q) => $q->whereHas('payrollRun', fn ($q) => $q->where('payroll_month', $this->month)),
            )
            ->get()
            ->sortBy(fn (PayrollRunEmployee $r) => [$r->employee === null, $r->employee?->employee_code])
            ->values();

        // Structure components first in their configured sequence, then
        // run-only lines (payroll inputs, TDS, loan recovery) by label.
        $lines = $this->rows->flatMap->lines;

        foreach ([SalaryComponent::TYPE_EARNING, SalaryComponent::TYPE_DEDUCTION, SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION] as $type) {
            $this->columns[$type] = $lines
                ->where('component_type', $type)
                ->sortBy([
                    fn ($a, $b) => ($a->component === null) <=> ($b->component === null),
                    fn ($a, $b) => ($a->component?->sequence ?? 0) <=> ($b->component?->sequence ?? 0),
                    fn ($a, $b) => strcmp($a->label, $b->label),
                ])
                ->pluck('label')
                ->unique()
                ->values()
                ->all();
        }
    }
}
