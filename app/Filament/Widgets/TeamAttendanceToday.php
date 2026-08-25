<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AttendanceRegister;
use App\Models\Employee;
use App\Services\AttendanceMonthlySummaryService;
use Filament\Widgets\Widget;

/**
 * Manager-only Dashboard widget: today's attendance status for each direct report (not the
 * full subordinate tree — same scope as MyTeamOverview). Reuses
 * AttendanceMonthlySummaryService::buildForEmployee() one day at a time so "today" resolves
 * through the exact same holiday/leave/weekly-off/punch rules as every other attendance
 * screen, instead of re-deriving that logic here. Direct-report lists are small enough that
 * a hand-rolled table (matching the style of AttendanceRegister/AttendanceMonthlyView) is
 * simpler than wiring up Filament's Table component for computed, non-Eloquent rows.
 */
class TeamAttendanceToday extends Widget
{
    protected static ?int $sort = 3;

    protected static string $view = 'filament.widgets.team-attendance-today';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $employeeId = auth()->user()?->employee_id;

        return $employeeId !== null && Employee::query()->where('reporting_manager_id', $employeeId)->exists();
    }

    /**
     * @return array<int, array{employee: Employee, cell: array{code: string, label: string, first_in: ?string, last_out: ?string, hours: ?float}}>
     */
    public function rows(): array
    {
        $employeeId = auth()->user()->employee_id;
        $today = now()->startOfDay();
        $summaryService = app(AttendanceMonthlySummaryService::class);

        return Employee::query()
            ->where('reporting_manager_id', $employeeId)
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $report) use ($summaryService, $today) {
                $days = $summaryService->buildForEmployee($report, $today, $today);

                return [
                    'employee' => $report,
                    'cell' => $days[$today->toDateString()] ?? ['code' => '', 'label' => '—', 'first_in' => null, 'last_out' => null, 'hours' => null],
                ];
            })
            ->all();
    }

    public function cellStyle(string $code): string
    {
        $colors = AttendanceMonthlySummaryService::CODE_COLORS[$code] ?? null;

        return $colors ? "background-color:{$colors['bg']};color:{$colors['text']};" : '';
    }

    public function registerUrl(): string
    {
        return AttendanceRegister::getUrl();
    }
}
