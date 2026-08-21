<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

/**
 * HR/manager-facing, on-screen Work From Home attendance report: in/out time, hours
 * completed, late mark, and completed/incomplete work-hours status for every WFH day.
 * Visibility follows the same team-scoping as every other attendance surface
 * (App\Filament\Concerns\ScopesToOwnTeam) — HR-tier sees everyone, a Manager sees their
 * own team, an Employee sees only their own days. An Excel version of the same data is
 * available from the Reports page ("Work From Home" card).
 */
class WfhReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'WFH Report';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.wfh-report';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user->can('attendance.view') || ($user->employee?->directReports()->exists() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ScopesToOwnTeam::apply(
                Attendance::query()->with('employee')->where('status', Attendance::STATUS_WFH),
                auth()->user(),
                'attendance.view',
            ))
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('attendance_date')->label('Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('first_in')->label('In Time')->time('H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('last_out')->label('Out Time')->time('H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('effective_hours')->label('Hours Completed')->placeholder('—'),
                Tables\Columns\TextColumn::make('late_minutes')
                    ->label('Late Mark')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "Late by {$state} min" : 'On Time')
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('completion')
                    ->label('Work Hours Status')
                    ->badge()
                    ->state(function (Attendance $record) {
                        $shift = app(AttendanceService::class)->activeShiftForEmployee($record->employee, $record->attendance_date);
                        $minFullDayHours = $shift ? (float) $shift->min_full_day_hours : null;

                        return $minFullDayHours !== null && (float) ($record->effective_hours ?? 0) >= $minFullDayHours
                            ? 'Completed'
                            : 'Incomplete';
                    })
                    ->color(fn (string $state) => $state === 'Completed' ? 'success' : 'warning'),
            ])
            ->filters([
                Filter::make('attendance_date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))),
            ])
            ->defaultSort('attendance_date', 'desc');
    }
}
