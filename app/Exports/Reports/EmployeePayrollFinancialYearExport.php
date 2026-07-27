<?php

namespace App\Exports\Reports;

use App\Models\FinancialYear;
use App\Models\PayrollRunEmployee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeePayrollFinancialYearExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly int $employeeId, private readonly FinancialYear $financialYear) {}

    public function headings(): array
    {
        return ['Payroll Month', 'Paid Days', 'LOP Days', 'Gross Earnings', 'Total Deductions', 'Net Pay', 'Status'];
    }

    public function collection()
    {
        return PayrollRunEmployee::query()
            ->where('employee_id', $this->employeeId)
            ->with('payrollRun')
            ->whereHas('payrollRun', fn ($q) => $q->whereBetween('payroll_month', [
                $this->financialYear->start_date->format('Y-m'),
                $this->financialYear->end_date->format('Y-m'),
            ]))
            ->get()
            ->sortBy('payrollRun.payroll_month')
            ->map(fn (PayrollRunEmployee $runEmployee) => [
                $runEmployee->payrollRun?->payroll_month,
                $runEmployee->paid_days,
                $runEmployee->lop_days,
                $runEmployee->gross_earnings,
                $runEmployee->total_deductions,
                $runEmployee->net_pay,
                ucfirst($runEmployee->status),
            ])
            ->values();
    }
}
