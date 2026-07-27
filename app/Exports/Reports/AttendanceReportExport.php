<?php

namespace App\Exports\Reports;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceReportExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Date', 'Status', 'First In', 'Last Out', 'Total Hours', 'Late Minutes'];
    }

    public function collection()
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $query = ScopesToOwnTeam::apply(
            Attendance::query()->with('employee')->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()]),
            $this->user,
            'attendance.view',
        );

        return $query->orderBy('attendance_date')->get()->map(fn (Attendance $attendance) => [
            $attendance->employee?->employee_code,
            $attendance->employee?->full_name,
            $attendance->attendance_date->toDateString(),
            Attendance::STATUSES[$attendance->status] ?? $attendance->status,
            $attendance->first_in,
            $attendance->last_out,
            $attendance->total_hours,
            $attendance->late_minutes,
        ]);
    }
}
