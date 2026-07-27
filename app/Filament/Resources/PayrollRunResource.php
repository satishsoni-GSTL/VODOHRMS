<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollRunResource\Pages;
use App\Models\PayrollRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Payroll Runs';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('payroll_month')
                    ->label('Payroll Month')
                    ->placeholder('YYYY-MM')
                    ->default(now()->format('Y-m'))
                    ->required()
                    ->maxLength(7),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('payroll_month', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('payroll_month')->sortable(),
                Tables\Columns\TextColumn::make('company.name')->searchable(),
                Tables\Columns\TextColumn::make('employees_count')->counts('employees')->label('Employees'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PayrollRun::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        PayrollRun::STATUS_LOCKED, PayrollRun::STATUS_FINALIZED => 'success',
                        PayrollRun::STATUS_REOPENED => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PayrollRun::STATUSES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->url(fn (PayrollRun $record) => static::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'view' => Pages\ManagePayrollRun::route('/{record}'),
        ];
    }
}
