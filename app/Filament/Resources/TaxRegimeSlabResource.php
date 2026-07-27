<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRegimeSlabResource\Pages;
use App\Models\TaxRegimeSlab;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRegimeSlabResource extends Resource
{
    protected static ?string $model = TaxRegimeSlab::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?string $navigationLabel = 'Tax Slabs';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('financial_year_id')->relationship('financialYear', 'name')->required()->searchable()->preload(),
                Forms\Components\Select::make('regime')
                    ->options([TaxRegimeSlab::REGIME_OLD => 'Old Regime', TaxRegimeSlab::REGIME_NEW => 'New Regime'])
                    ->required(),
                Forms\Components\TextInput::make('income_from')->required()->numeric()->prefix('₹'),
                Forms\Components\TextInput::make('income_to')->numeric()->prefix('₹')->helperText('Leave blank for no upper bound'),
                Forms\Components\TextInput::make('tax_percent')->required()->numeric()->suffix('%'),
                Forms\Components\TextInput::make('sequence')->required()->numeric()->default(0),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->columns([
                Tables\Columns\TextColumn::make('financialYear.name')->label('FY'),
                Tables\Columns\TextColumn::make('regime')->badge(),
                Tables\Columns\TextColumn::make('income_from')->money('INR'),
                Tables\Columns\TextColumn::make('income_to')->money('INR')->placeholder('No limit'),
                Tables\Columns\TextColumn::make('tax_percent')->suffix('%'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('financial_year_id')->relationship('financialYear', 'name')->label('Financial Year'),
                Tables\Filters\SelectFilter::make('regime')->options([
                    TaxRegimeSlab::REGIME_OLD => 'Old Regime', TaxRegimeSlab::REGIME_NEW => 'New Regime',
                ]),
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
            'index' => Pages\ListTaxRegimeSlabs::route('/'),
            'create' => Pages\CreateTaxRegimeSlab::route('/create'),
            'edit' => Pages\EditTaxRegimeSlab::route('/{record}/edit'),
        ];
    }
}
