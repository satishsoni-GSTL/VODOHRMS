<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MyAttendance;
use App\Services\AttendanceMonthlySummaryService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Every employee's own current-month attendance at a glance, landing on the main Dashboard.
 * Reuses AttendanceMonthlySummaryService::buildForEmployee — the same day-cell logic behind
 * the My Attendance page, the team pages, and the exports — so the counts here always agree
 * with what those show. Click-through via Stat::url() into the full My Attendance page.
 */
class MyAttendanceOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->user()?->employee_id !== null;
    }

    protected function getStats(): array
    {
        $employee = auth()->user()->employee;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->copy()->endOfMonth();

        $days = app(AttendanceMonthlySummaryService::class)->buildForEmployee($employee, $monthStart, $monthEnd);
        $counts = array_count_values(array_column($days, 'code'));
        $hoursValues = array_filter(array_column($days, 'hours'), fn ($h) => $h !== null);

        $today = $days[now()->toDateString()] ?? ['code' => '', 'label' => '—', 'first_in' => null];
        $todayLabel = $today['first_in'] ? "{$today['code']} · in {$today['first_in']}" : ($today['code'] ?: '—');

        $url = MyAttendance::getUrl();

        return [
            Stat::make("Today's Status", $todayLabel)
                ->color($this->colorFor($today['code']))
                ->url($url),
            Stat::make('Present Days (This Month)', $counts[AttendanceMonthlySummaryService::CODE_PRESENT] ?? 0)
                ->color('success')
                ->url($url),
            Stat::make('Absent Days (This Month)', $counts[AttendanceMonthlySummaryService::CODE_ABSENT] ?? 0)
                ->color('danger')
                ->url($url),
            Stat::make('Leave Days (This Month)', $counts[AttendanceMonthlySummaryService::CODE_LEAVE] ?? 0)
                ->color('warning')
                ->url($url),
            Stat::make('Total Hours (This Month)', round(array_sum($hoursValues), 2))
                ->description(count($hoursValues) > 0 ? 'Avg '.round(array_sum($hoursValues) / count($hoursValues), 2).' hrs/day' : null)
                ->url($url),
        ];
    }

    private function colorFor(string $code): string
    {
        return match ($code) {
            AttendanceMonthlySummaryService::CODE_PRESENT, AttendanceMonthlySummaryService::CODE_WFH, AttendanceMonthlySummaryService::CODE_ON_DUTY => 'success',
            AttendanceMonthlySummaryService::CODE_HALF_DAY, AttendanceMonthlySummaryService::CODE_MISSING_PUNCH => 'warning',
            AttendanceMonthlySummaryService::CODE_ABSENT => 'danger',
            default => 'gray',
        };
    }
}
