<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeSalaryStructureResource\Pages;
use App\Models\EmployeeSalaryStructure;
use App\Models\SalaryComponent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeSalaryStructureResource extends Resource
{
    protected static ?string $model = EmployeeSalaryStructure::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-rupee';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Salary Structures';

    protected static ?int $navigationSort = 2;

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
                Forms\Components\DatePicker::make('effective_from')->default(now())->required(),
                Forms\Components\TextInput::make('annual_ctc')->numeric()->required()->prefix('₹')->live(onBlur: true),
                Forms\Components\Repeater::make('lines')
                    ->label('Earning Components (monthly amounts)')
                    ->schema([
                        Forms\Components\Select::make('salary_component_id')
                            ->label('Component')
                            ->options(fn () => SalaryComponent::query()->active()->where('type', SalaryComponent::TYPE_EARNING)->orderBy('sequence')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('monthly_amount')->numeric()->required()->prefix('₹'),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Statutory deductions (PF, ESIC, Professional Tax) and employer contributions are computed automatically from the Basic component.'),
                Forms\Components\Textarea::make('remarks')->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('effective_from')->date()->sortable(),
                Tables\Columns\TextColumn::make('effective_to')->date()->placeholder('Current'),
                Tables\Columns\TextColumn::make('annual_ctc')->money('INR'),
                Tables\Columns\TextColumn::make('monthly_gross')->money('INR'),
                Tables\Columns\TextColumn::make('increment_percent')->suffix('%')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Current Structure'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeSalaryStructures::route('/'),
            'create' => Pages\CreateEmployeeSalaryStructure::route('/create'),
            'view' => Pages\ViewEmployeeSalaryStructure::route('/{record}'),
        ];
    }
}
