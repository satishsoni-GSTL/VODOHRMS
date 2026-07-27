<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryComponentResource\Pages;
use App\Models\SalaryComponent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryComponentResource extends Resource
{
    protected static ?string $model = SalaryComponent::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->options([
                                SalaryComponent::TYPE_EARNING => 'Earning',
                                SalaryComponent::TYPE_DEDUCTION => 'Deduction',
                                SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION => 'Employer Contribution',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('sequence')->numeric()->default(0)->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Calculation (used to auto-compute Deduction / Employer Contribution lines)')
                    ->schema([
                        Forms\Components\Select::make('calculation_type')
                            ->options([
                                SalaryComponent::CALC_FIXED => 'Fixed',
                                SalaryComponent::CALC_PERCENTAGE => 'Percentage of Basic',
                            ])
                            ->default('fixed')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('default_percentage')
                            ->numeric()->suffix('%')
                            ->visible(fn (Forms\Get $get) => $get('calculation_type') === SalaryComponent::CALC_PERCENTAGE),
                        Forms\Components\TextInput::make('default_amount')
                            ->numeric()->prefix('₹')
                            ->visible(fn (Forms\Get $get) => $get('calculation_type') === SalaryComponent::CALC_FIXED),
                    ])
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), [SalaryComponent::TYPE_DEDUCTION, SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION]))
                    ->columns(3),
                Forms\Components\Section::make('Flags')
                    ->schema([
                        Forms\Components\Toggle::make('is_taxable')->default(true),
                        Forms\Components\Toggle::make('is_pf_applicable')->default(false),
                        Forms\Components\Toggle::make('is_esic_applicable')->default(false),
                        Forms\Components\Toggle::make('is_prorated')->default(true)
                            ->helperText('Reduced proportionally for LOP days during payroll'),
                        Forms\Components\Toggle::make('is_ctc_component')->default(true),
                        Forms\Components\Toggle::make('is_gross_component')->default(true),
                        Forms\Components\Toggle::make('show_on_payslip')->default(true),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->columns([
                Tables\Columns\TextColumn::make('sequence')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('calculation_type'),
                Tables\Columns\IconColumn::make('is_prorated')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    SalaryComponent::TYPE_EARNING => 'Earning',
                    SalaryComponent::TYPE_DEDUCTION => 'Deduction',
                    SalaryComponent::TYPE_EMPLOYER_CONTRIBUTION => 'Employer Contribution',
                ]),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListSalaryComponents::route('/'),
            'create' => Pages\CreateSalaryComponent::route('/create'),
            'edit' => Pages\EditSalaryComponent::route('/{record}/edit'),
        ];
    }
}
