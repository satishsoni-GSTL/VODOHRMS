<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\EmployeeTdsScheduleResource\Pages;
use App\Models\EmployeeTdsSchedule;
use App\Services\TdsScheduleService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EmployeeTdsScheduleResource extends Resource
{
    protected static ?string $model = EmployeeTdsSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?string $navigationLabel = 'TDS Schedule';

    protected static ?string $modelLabel = 'TDS schedule';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('payroll_month')
            ->defaultGroup(
                Group::make('employee_id')
                    ->label('Employee')
                    ->getTitleFromRecordUsing(fn (EmployeeTdsSchedule $record) => "{$record->employee->employee_code} - {$record->employee->full_name} ({$record->financialYear->name})")
                    ->collapsible()
            )
            ->paginated([12, 24, 60, 120])
            ->defaultPaginationPageOption(60)
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('payroll_month')
                    ->label('Month')
                    ->formatStateUsing(fn (string $state) => Carbon::createFromFormat('Y-m', $state)->format('M Y')),
                Tables\Columns\TextInputColumn::make('amount')
                    ->label('TDS Amount')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->disabled(fn (EmployeeTdsSchedule $record) => ! auth()->user()->can('tax.manage')
                        || app(TdsScheduleService::class)->isLocked($record))
                    ->updateStateUsing(function ($state, EmployeeTdsSchedule $record) {
                        try {
                            app(TdsScheduleService::class)->updateAmount($record, (float) $state, auth()->user());
                            Notification::make()->title('TDS saved')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }

                        return $record->fresh()->amount;
                    })
                    ->summarize(Sum::make()->label('Total')->money('INR')),
                Tables\Columns\IconColumn::make('is_manual')->label('Edited')->boolean(),
                Tables\Columns\IconColumn::make('locked')
                    ->label('Payroll Finalized')
                    ->boolean()
                    ->getStateUsing(fn (EmployeeTdsSchedule $record) => app(TdsScheduleService::class)->isLocked($record)),
                Tables\Columns\TextColumn::make('updatedBy.name')->label('Last Saved By')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Last Saved')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('financial_year_id')->relationship('financialYear', 'name')->label('Financial Year'),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable()
                    ->label('Employee'),
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Clear schedule')
                    ->modalDescription('Removes the selected months. Payroll falls back to auto-calculated TDS for any month without a saved schedule.')
                    ->visible(fn () => auth()->user()->can('tax.manage')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery()->with(['employee', 'financialYear']), auth()->user(), 'tax.manage');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeTdsSchedules::route('/'),
        ];
    }
}
