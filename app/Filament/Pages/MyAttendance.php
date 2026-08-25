<?php

namespace App\Filament\Pages;

use App\Services\AttendanceMonthlySummaryService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * Self-service counterpart to AttendanceMonthlyView/AttendanceRegister: every employee's
 * own monthly attendance, one row per calendar day, with in/out times and hours — no
 * permission gate, no other employees' data. Managers who also want their reports' data
 * use the existing team pages (or the "Team Attendance Today" dashboard widget).
 */
class MyAttendance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'My Attendance';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.my-attendance';

    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->employee_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('reports.download', ['type' => 'my_attendance', 'month' => $this->month ?: now()->format('Y-m')]))
                ->openUrlInNewTab(),
        ];
    }

    public function updatedMonth(): void
    {
        // Nothing to reset — rows() re-runs on every render, no pagination for a single employee.
    }

    public function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month ?: now()->format('Y-m'))->startOfMonth();
    }

    public function monthEnd(): Carbon
    {
        return $this->monthStart()->copy()->endOfMonth();
    }

    /**
     * @return array<string, array{code: string, label: string, first_in: ?string, last_out: ?string, hours: ?float}>
     */
    public function rows(): array
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            return [];
        }

        return app(AttendanceMonthlySummaryService::class)->buildForEmployee($employee, $this->monthStart(), $this->monthEnd());
    }

    /**
     * @return array{P: int, HD: int, WFH: int, OD: int, L: int, H: int, WO: int, A: int, MP: int, hours: float, avg_hours: float}
     */
    public function totals(): array
    {
        $days = $this->rows();
        $counts = array_count_values(array_column($days, 'code'));
        $hoursValues = array_filter(array_column($days, 'hours'), fn ($h) => $h !== null);

        return [
            'P' => $counts[AttendanceMonthlySummaryService::CODE_PRESENT] ?? 0,
            'HD' => $counts[AttendanceMonthlySummaryService::CODE_HALF_DAY] ?? 0,
            'WFH' => $counts[AttendanceMonthlySummaryService::CODE_WFH] ?? 0,
            'OD' => $counts[AttendanceMonthlySummaryService::CODE_ON_DUTY] ?? 0,
            'L' => $counts[AttendanceMonthlySummaryService::CODE_LEAVE] ?? 0,
            'H' => $counts[AttendanceMonthlySummaryService::CODE_HOLIDAY] ?? 0,
            'WO' => $counts[AttendanceMonthlySummaryService::CODE_WEEKLY_OFF] ?? 0,
            'A' => $counts[AttendanceMonthlySummaryService::CODE_ABSENT] ?? 0,
            'MP' => $counts[AttendanceMonthlySummaryService::CODE_MISSING_PUNCH] ?? 0,
            'hours' => round(array_sum($hoursValues), 2),
            'avg_hours' => count($hoursValues) > 0 ? round(array_sum($hoursValues) / count($hoursValues), 2) : 0.0,
        ];
    }

    public function cellStyle(string $code): string
    {
        $colors = AttendanceMonthlySummaryService::CODE_COLORS[$code] ?? null;

        return $colors ? "background-color:{$colors['bg']};color:{$colors['text']};" : '';
    }
}
