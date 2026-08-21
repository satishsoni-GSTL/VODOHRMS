<?php

namespace App\Exports\Reports;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WfhReportExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        return [
            'Employee Code', 'Name', 'Date', 'In Time', 'Out Time',
            'Hours Completed', 'Late Mark (min)', 'Work Hours Status',
        ];
    }

    public function collection()
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $attendanceService = app(AttendanceService::class);

        $query = ScopesToOwnTeam::apply(
            Attendance::query()->with('employee')
                ->where('status', Attendance::STATUS_WFH)
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()]),
            $this->user,
            'attendance.view',
        );

        return $query->orderBy('attendance_date')->get()->map(function (Attendance $attendance) use ($attendanceService) {
            $shift = $attendanceService->activeShiftForEmployee($attendance->employee, $attendance->attendance_date);
            $minFullDayHours = $shift ? (float) $shift->min_full_day_hours : null;
            $completed = $minFullDayHours !== null && (float) ($attendance->effective_hours ?? 0) >= $minFullDayHours;

            return [
                $attendance->employee?->employee_code,
                $attendance->employee?->full_name,
                $attendance->attendance_date->toDateString(),
                $attendance->first_in,
                $attendance->last_out,
                $attendance->effective_hours,
                $attendance->late_minutes > 0 ? $attendance->late_minutes : 0,
                $completed ? 'Completed' : 'Incomplete',
            ];
        });
    }
}
