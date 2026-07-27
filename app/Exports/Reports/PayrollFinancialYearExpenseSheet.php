<?php

namespace App\Exports\Reports;

use App\Models\ExpenseClaim;
use App\Models\FinancialYear;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollFinancialYearExpenseSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly FinancialYear $financialYear) {}

    public function title(): string
    {
        return 'Expenses';
    }

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Department', 'Claim Number', 'Claim Date', 'Requested Amount', 'Approved Amount', 'Status'];
    }

    public function collection()
    {
        return ExpenseClaim::query()
            ->with(['employee.department'])
            ->whereBetween('claim_date', [$this->financialYear->start_date, $this->financialYear->end_date])
            ->orderBy('claim_date')
            ->get()
            ->map(fn (ExpenseClaim $claim) => [
                $claim->employee?->employee_code,
                $claim->employee?->full_name,
                $claim->employee?->department?->name,
                $claim->claim_number,
                $claim->claim_date->toDateString(),
                $claim->total_requested_amount,
                $claim->total_approved_amount,
                ucfirst(str_replace('_', ' ', $claim->status)),
            ]);
    }
}
