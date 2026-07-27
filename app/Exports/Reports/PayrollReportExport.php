<?php

namespace App\Exports\Reports;

use App\Models\PayrollRunEmployee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PayrollReportExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly string $month) {}

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Paid Days', 'LOP Days', 'Gross Earnings', 'Total Deductions', 'Employer Contributions', 'Net Pay', 'Status'];
    }

    public function collection()
    {
        return PayrollRunEmployee::query()
            ->with('employee')
            ->whereHas('payrollRun', fn ($q) => $q->where('payroll_month', $this->month))
            ->get()
            ->map(fn (PayrollRunEmployee $runEmployee) => [
                $runEmployee->employee?->employee_code,
                $runEmployee->employee?->full_name,
                $runEmployee->paid_days,
                $runEmployee->lop_days,
                $runEmployee->gross_earnings,
                $runEmployee->total_deductions,
                $runEmployee->employer_contributions,
                $runEmployee->net_pay,
                ucfirst($runEmployee->status),
            ]);
    }
}
