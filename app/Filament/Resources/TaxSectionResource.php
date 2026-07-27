<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxSectionResource\Pages;
use App\Models\TaxSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxSectionResource extends Resource
{
    protected static ?string $model = TaxSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('financial_year_id')->relationship('financialYear', 'name')->required()->searchable()->preload(),
                Forms\Components\TextInput::make('code')->required()->maxLength(50)->placeholder('80C'),
                Forms\Components\TextInput::make('name')->required()->maxLength(150),
                Forms\Components\TextInput::make('max_limit')->numeric()->prefix('₹'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('financialYear.name')->label('FY')->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('max_limit')->money('INR'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            'index' => Pages\ListTaxSections::route('/'),
            'create' => Pages\CreateTaxSection::route('/create'),
            'edit' => Pages\EditTaxSection::route('/{record}/edit'),
        ];
    }
}
