<?php

namespace App\Exports\Reports;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Models\EmployeeLoan;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoanReportExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly User $user) {}

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Type', 'Requested Amount', 'Approved Amount', 'Outstanding Balance', 'Status', 'Request Date'];
    }

    public function collection()
    {
        $query = ScopesToOwnTeam::apply(EmployeeLoan::query()->with('employee'), $this->user, 'loan.view');

        return $query->orderByDesc('request_date')->get()->map(fn (EmployeeLoan $loan) => [
            $loan->employee?->employee_code,
            $loan->employee?->full_name,
            $loan->type === EmployeeLoan::TYPE_SALARY_ADVANCE ? 'Salary Advance' : 'Loan',
            $loan->requested_amount,
            $loan->approved_amount,
            $loan->outstanding_balance,
            EmployeeLoan::STATUSES[$loan->status] ?? $loan->status,
            $loan->request_date->toDateString(),
        ]);
    }
}
