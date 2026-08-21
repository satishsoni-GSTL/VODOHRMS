<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BiometricDeviceResource\Pages;
use App\Models\BiometricDevice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BiometricDeviceResource extends Resource
{
    protected static ?string $model = BiometricDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(150),
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->helperText('A short internal identifier for this device.'),
            Forms\Components\Select::make('branch_id')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('location')
                ->maxLength(150)
                ->helperText('E.g. LAN IP or physical location, for your own reference.'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->toggleable(),
                Tables\Columns\TextColumn::make('location')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('last_synced_at')->dateTime('d M Y H:i')->since()->sortable(),
                Tables\Columns\TextColumn::make('last_synced_ip')->label('Last Sync IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBiometricDevices::route('/'),
            'create' => Pages\CreateBiometricDevice::route('/create'),
            'edit' => Pages\EditBiometricDevice::route('/{record}/edit'),
        ];
    }
}
