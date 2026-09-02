<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollInputResource\Pages;
use App\Models\PayrollInput;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollInputResource extends Resource
{
    protected static ?string $model = PayrollInput::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name'])
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('payroll_month')
                    ->label('Payroll Month')
                    ->placeholder('YYYY-MM')
                    ->default(now()->format('Y-m'))
                    ->required()
                    ->maxLength(7),
                Forms\Components\Select::make('type')->options(PayrollInput::TYPES)->required(),
                Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('₹'),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('payroll_month'),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn (string $state) => PayrollInput::TYPES[$state] ?? $state)->badge(),
                Tables\Columns\TextColumn::make('amount')->money('INR'),
                Tables\Columns\TextColumn::make('reason')->limit(40),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(PayrollInput::TYPES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollInputs::route('/'),
            'create' => Pages\CreatePayrollInput::route('/create'),
            'edit' => Pages\EditPayrollInput::route('/{record}/edit'),
        ];
    }
}
