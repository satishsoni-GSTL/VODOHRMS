<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRegimeConfigResource\Pages;
use App\Models\TaxRegimeConfig;
use App\Models\TaxRegimeSlab;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRegimeConfigResource extends Resource
{
    protected static ?string $model = TaxRegimeConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?string $navigationLabel = 'Regime Configuration';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('financial_year_id')->relationship('financialYear', 'name')->required()->searchable()->preload(),
                Forms\Components\Select::make('regime')
                    ->options([TaxRegimeSlab::REGIME_OLD => 'Old Regime', TaxRegimeSlab::REGIME_NEW => 'New Regime'])
                    ->required(),
                Forms\Components\TextInput::make('standard_deduction')->required()->numeric()->prefix('₹')->default(50000),
                Forms\Components\TextInput::make('rebate_limit_income')->numeric()->prefix('₹')->helperText('Taxable income at/below which the rebate applies (Sec 87A)'),
                Forms\Components\TextInput::make('rebate_max_amount')->numeric()->prefix('₹'),
                Forms\Components\TextInput::make('cess_percent')->required()->numeric()->suffix('%')->default(4),
                Forms\Components\Toggle::make('regime_change_allowed')->default(true),
                Forms\Components\Repeater::make('surcharge_rules')
                    ->schema([
                        Forms\Components\TextInput::make('above_income')->numeric()->required()->prefix('₹'),
                        Forms\Components\TextInput::make('percent')->numeric()->required()->suffix('%'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('financialYear.name')->label('FY'),
                Tables\Columns\TextColumn::make('regime')->badge(),
                Tables\Columns\TextColumn::make('standard_deduction')->money('INR'),
                Tables\Columns\TextColumn::make('rebate_limit_income')->money('INR')->placeholder('—'),
                Tables\Columns\TextColumn::make('cess_percent')->suffix('%'),
                Tables\Columns\IconColumn::make('regime_change_allowed')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('financial_year_id')->relationship('financialYear', 'name')->label('Financial Year'),
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
            'index' => Pages\ListTaxRegimeConfigs::route('/'),
            'create' => Pages\CreateTaxRegimeConfig::route('/create'),
            'edit' => Pages\EditTaxRegimeConfig::route('/{record}/edit'),
        ];
    }
}
