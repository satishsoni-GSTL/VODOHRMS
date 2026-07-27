<?php

namespace App\Exports\Reports;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Models\LeaveApplication;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeaveReportExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Leave Type', 'From', 'To', 'Days', 'Status'];
    }

    public function collection()
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $query = ScopesToOwnTeam::apply(
            LeaveApplication::query()
                ->with(['employee', 'leaveType'])
                ->whereBetween('from_date', [$start->toDateString(), $end->toDateString()]),
            $this->user,
            'leave.view',
        );

        return $query->orderBy('from_date')->get()->map(fn (LeaveApplication $leave) => [
            $leave->employee?->employee_code,
            $leave->employee?->full_name,
            $leave->leaveType?->name,
            $leave->from_date->toDateString(),
            $leave->to_date->toDateString(),
            $leave->days,
            ucfirst(str_replace('_', ' ', $leave->status)),
        ]);
    }
}
