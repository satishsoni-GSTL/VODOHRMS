<?php

namespace App\Filament\Widgets;

use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmployeeStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('employee.view') ?? false;
    }

    protected function getStats(): array
    {
        $total = Employee::query()->count();
        $active = Employee::query()->where('status', Employee::STATUS_ACTIVE)->count();
        $probation = Employee::query()->where('status', Employee::STATUS_PROBATION)->count();
        $notice = Employee::query()->where('status', Employee::STATUS_NOTICE_PERIOD)->count();
        $newJoiners = Employee::query()->whereMonth('date_of_joining', now()->month)
            ->whereYear('date_of_joining', now()->year)->count();
        $exited = Employee::query()->whereIn('status', [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED, Employee::STATUS_EXITED])->count();

        return [
            Stat::make('Total Employees', $total),
            Stat::make('Active Employees', $active)->color('success'),
            Stat::make('On Probation', $probation)->color('warning'),
            Stat::make('On Notice Period', $notice)->color('warning'),
            Stat::make('New Joiners (This Month)', $newJoiners)->color('info'),
            Stat::make('Exited / Resigned / Terminated', $exited)->color('danger'),
        ];
    }
}
