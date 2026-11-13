<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('city')->maxLength(255),
                Forms\Components\TextInput::make('state')->maxLength(255),
                Forms\Components\TextInput::make('country')->default('India')->required(),
                Forms\Components\TextInput::make('pincode')->maxLength(10),
                Forms\Components\Toggle::make('is_active')->default(true)->required(),
                Forms\Components\Section::make('Attendance geofence')
                    ->description('Office staff at this location can punch attendance from the mobile app only when they are within this radius of the office. Leave the radius empty to disable app punching here (biometric only). Tip: in Google Maps, right-click the office and click the coordinates to copy them.')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')->numeric()->minValue(-90)->maxValue(90)
                            ->requiredWith('geofence_radius_m')->placeholder('19.0760000'),
                        Forms\Components\TextInput::make('longitude')->numeric()->minValue(-180)->maxValue(180)
                            ->requiredWith('geofence_radius_m')->placeholder('72.8777000'),
                        Forms\Components\TextInput::make('geofence_radius_m')->label('Radius (metres)')
                            ->numeric()->integer()->minValue(20)->maxValue(5000)->placeholder('200'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('city')->searchable(),
                Tables\Columns\TextColumn::make('state')->searchable(),
                Tables\Columns\TextColumn::make('geofence_radius_m')->label('Geofence')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} m" : null)->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
