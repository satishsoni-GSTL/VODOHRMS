<?php

namespace App\Filament\Widgets;

use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyTeamOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $employeeId = auth()->user()?->employee_id;

        return $employeeId !== null && Employee::query()->where('reporting_manager_id', $employeeId)->exists();
    }

    protected function getStats(): array
    {
        $employeeId = auth()->user()->employee_id;

        $directReports = Employee::query()->where('reporting_manager_id', $employeeId);

        return [
            Stat::make('My Direct Reports', (clone $directReports)->count()),
            Stat::make('Active', (clone $directReports)->where('status', Employee::STATUS_ACTIVE)->count())->color('success'),
            Stat::make('On Probation', (clone $directReports)->where('status', Employee::STATUS_PROBATION)->count())->color('warning'),
        ];
    }
}
