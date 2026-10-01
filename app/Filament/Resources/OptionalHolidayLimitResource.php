<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OptionalHolidayLimitResource\Pages;
use App\Models\OptionalHolidayLimit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class OptionalHolidayLimitResource extends Resource
{
    protected static ?string $model = OptionalHolidayLimit::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Optional Holiday Limits';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()->can('attendance.manage');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('year')
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->default(fn () => (int) now()->format('Y'))
                    ->required()
                    // Checked on year (always filled) so the "all employees" default — a null
                    // employee_id, which a unique rule on that field would skip — can't be duplicated.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $get('employee_id')
                        ? $rule->where('employee_id', $get('employee_id'))
                        : $rule->whereNull('employee_id'))
                    ->validationMessages(['unique' => 'A limit for this employee and year already exists.']),
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->helperText('Leave empty to set the default limit for all employees this year.')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name']),
                Forms\Components\TextInput::make('max_claims')
                    ->label('Max optional holidays')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255)
                    ->required(),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->placeholder('All employees')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->placeholder('Default'),
                Tables\Columns\TextColumn::make('max_claims')->label('Max Optional Holidays'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->options(fn () => OptionalHolidayLimit::query()->distinct()->orderByDesc('year')->pluck('year', 'year')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOptionalHolidayLimits::route('/'),
        ];
    }
}
