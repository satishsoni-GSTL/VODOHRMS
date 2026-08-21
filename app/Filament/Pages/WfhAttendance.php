<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Self-service Check-In/Check-Out for an employee's own approved Work From Home days,
 * plus a personal history of this month's WFH attendance (in/out time, hours, late mark,
 * completion status). Approving a WorkFromHomeRequest (see WorkFromHomeRequestResource)
 * is what actually marks a day as WFH on the attendance register — this page only records
 * punches against days already marked that way.
 */
class WfhAttendance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'WFH Clock In/Out';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.wfh-attendance';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->employee !== null;
    }

    public function todayAttendance(): ?Attendance
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            return null;
        }

        return Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', Carbon::today()->toDateString())
            ->first();
    }

    public function checkIn(): void
    {
        $this->punch('clockIn', 'Checked in');
    }

    public function checkOut(): void
    {
        $this->punch('clockOut', 'Checked out');
    }

    private function punch(string $method, string $successMessage): void
    {
        try {
            app(WorkFromHomeService::class)->{$method}(auth()->user()->employee);
            Notification::make()->title($successMessage)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    /**
     * @return Collection<int, array{date: string, first_in: ?string, last_out: ?string, effective_hours: ?float, late_minutes: int, late_mark: bool, completed: bool}>
     */
    public function monthHistory(): Collection
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            return collect();
        }

        $attendanceService = app(AttendanceService::class);
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', Attendance::STATUS_WFH)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByDesc('attendance_date')
            ->get()
            ->map(function (Attendance $attendance) use ($attendanceService) {
                $minFullDayHours = $attendanceService->minFullDayHoursFor($attendance->employee, $attendance->attendance_date);

                return [
                    'date' => $attendance->attendance_date->toDateString(),
                    'first_in' => $attendance->first_in,
                    'last_out' => $attendance->display_last_out,
                    'effective_hours' => $attendance->effective_hours,
                    'late_minutes' => $attendance->late_minutes,
                    'late_mark' => $attendance->late_minutes > 0,
                    'completed' => (float) ($attendance->effective_hours ?? 0) >= $minFullDayHours,
                ];
            });
    }
}
