<?php

namespace App\Exports\Reports;

use App\Models\FinancialYear;
use App\Models\PayrollRunEmployee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollFinancialYearPayrollSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly FinancialYear $financialYear) {}

    public function title(): string
    {
        return 'Payroll';
    }

    public function headings(): array
    {
        return ['Payroll Month', 'Employee Code', 'Name', 'Department', 'Paid Days', 'LOP Days', 'Gross Earnings', 'Total Deductions', 'Employer Contributions', 'Net Pay', 'Status'];
    }

    public function collection()
    {
        return PayrollRunEmployee::query()
            ->with(['employee.department', 'payrollRun'])
            ->whereHas('payrollRun', fn ($q) => $q->whereBetween('payroll_month', [
                $this->financialYear->start_date->format('Y-m'),
                $this->financialYear->end_date->format('Y-m'),
            ]))
            ->get()
            ->sortBy([['payrollRun.payroll_month', 'asc'], ['employee.employee_code', 'asc']])
            ->map(fn (PayrollRunEmployee $runEmployee) => [
                $runEmployee->payrollRun?->payroll_month,
                $runEmployee->employee?->employee_code,
                $runEmployee->employee?->full_name,
                $runEmployee->employee?->department?->name,
                $runEmployee->paid_days,
                $runEmployee->lop_days,
                $runEmployee->gross_earnings,
                $runEmployee->total_deductions,
                $runEmployee->employer_contributions,
                $runEmployee->net_pay,
                ucfirst($runEmployee->status),
            ])
            ->values();
    }
}
