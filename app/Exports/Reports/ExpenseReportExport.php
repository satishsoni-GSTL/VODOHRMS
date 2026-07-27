<?php

namespace App\Exports\Reports;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Models\ExpenseClaim;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExpenseReportExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Claim Date', 'Requested Amount', 'Approved Amount', 'Status'];
    }

    public function collection()
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $query = ScopesToOwnTeam::apply(
            ExpenseClaim::query()->with('employee')->whereBetween('claim_date', [$start->toDateString(), $end->toDateString()]),
            $this->user,
            'expense.view',
        );

        return $query->orderBy('claim_date')->get()->map(fn (ExpenseClaim $claim) => [
            $claim->employee?->employee_code,
            $claim->employee?->full_name,
            $claim->claim_date->toDateString(),
            $claim->total_requested_amount,
            $claim->total_approved_amount,
            ucfirst(str_replace('_', ' ', $claim->status)),
        ]);
    }
}
