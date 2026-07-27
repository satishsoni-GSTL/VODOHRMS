<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HolidayResource\Pages;
use App\Models\Holiday;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\DatePicker::make('date')->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'national' => 'National Holiday', 'state' => 'State Holiday',
                        'company' => 'Company Holiday', 'optional' => 'Optional Holiday',
                    ])
                    ->default('company')
                    ->required(),
                Forms\Components\Select::make('company_id')->relationship('company', 'name')->searchable()->preload(),
                Forms\Components\Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload(),
                Forms\Components\Select::make('location_id')->relationship('location', 'name')->searchable()->preload(),
                Forms\Components\TextInput::make('state')->maxLength(100),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('company.name')->toggleable(),
                Tables\Columns\TextColumn::make('branch.name')->toggleable(),
                Tables\Columns\TextColumn::make('state')->toggleable(),
            ])
            ->defaultSort('date')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'national' => 'National', 'state' => 'State', 'company' => 'Company', 'optional' => 'Optional',
                ]),
                Tables\Filters\SelectFilter::make('company_id')->relationship('company', 'name'),
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
            'index' => Pages\ListHolidays::route('/'),
            'create' => Pages\CreateHoliday::route('/create'),
            'edit' => Pages\EditHoliday::route('/{record}/edit'),
        ];
    }
}
