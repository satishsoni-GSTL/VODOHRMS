<?php

namespace App\Exports\Reports;

use App\Models\User;
use App\Services\AttendanceMonthlySummaryService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Excel counterpart of the "My Attendance" self-service page: one row per calendar day for
 * the downloading user's own employee record only (never another employee's — see
 * ReportDownloadController, which authorizes this type on "has an employee record" rather
 * than the team-scoped attendance.view/manager check the other attendance exports use).
 */
class MyAttendanceExport implements FromArray, WithHeadings
{
    private const LABELS = [
        AttendanceMonthlySummaryService::CODE_PRESENT => 'Present',
        AttendanceMonthlySummaryService::CODE_HALF_DAY => 'Half Day',
        AttendanceMonthlySummaryService::CODE_WFH => 'Work From Home',
        AttendanceMonthlySummaryService::CODE_ON_DUTY => 'On Duty',
        AttendanceMonthlySummaryService::CODE_LEAVE => 'Leave',
        AttendanceMonthlySummaryService::CODE_HOLIDAY => 'Holiday',
        AttendanceMonthlySummaryService::CODE_WEEKLY_OFF => 'Weekly Off',
        AttendanceMonthlySummaryService::CODE_ABSENT => 'Absent',
        AttendanceMonthlySummaryService::CODE_MISSING_PUNCH => 'Missing Punch',
    ];

    public function __construct(private readonly string $month, private readonly User $user) {}

    public function headings(): array
    {
        return ['Date', 'Day', 'Status', 'First In', 'Last Out', 'Hours'];
    }

    public function array(): array
    {
        $employee = $this->user->employee;

        if (! $employee) {
            return [];
        }

        $monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $days = app(AttendanceMonthlySummaryService::class)->buildForEmployee($employee, $monthStart, $monthEnd);

        return collect($days)->map(fn (array $cell, string $date) => [
            $date,
            Carbon::parse($date)->format('l'),
            self::LABELS[$cell['code']] ?? '',
            $cell['first_in'],
            $cell['last_out'],
            $cell['hours'],
        ])->values()->all();
    }
}
