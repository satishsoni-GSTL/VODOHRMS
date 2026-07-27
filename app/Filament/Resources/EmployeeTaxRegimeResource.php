<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\EmployeeTaxRegimeResource\Pages;
use App\Models\EmployeeTaxRegime;
use App\Models\TaxRegimeSlab;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeTaxRegimeResource extends Resource
{
    protected static ?string $model = EmployeeTaxRegime::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?string $navigationLabel = 'Employee Regime';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name'])
                    ->preload()
                    ->required()
                    ->default(fn () => auth()->user()->employee_id)
                    ->disabled(fn () => ! auth()->user()->can('tax.manage'))
                    ->dehydrated(),
                Forms\Components\Select::make('financial_year_id')->relationship('financialYear', 'name')->required()->searchable()->preload(),
                Forms\Components\Select::make('selected_regime')
                    ->options([TaxRegimeSlab::REGIME_OLD => 'Old Regime', TaxRegimeSlab::REGIME_NEW => 'New Regime'])
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('selection_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('financialYear.name')->label('FY'),
                Tables\Columns\TextColumn::make('selected_regime')->badge(),
                Tables\Columns\TextColumn::make('selection_date')->date(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('financial_year_id')->relationship('financialYear', 'name')->label('Financial Year'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'tax.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeTaxRegimes::route('/'),
            'create' => Pages\CreateEmployeeTaxRegime::route('/create'),
        ];
    }
}
