<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'general' => 'General', 'flexible' => 'Flexible',
                        'rotational' => 'Rotational', 'custom' => 'Custom',
                    ])
                    ->default('general')
                    ->required(),
                Forms\Components\TimePicker::make('start_time')->seconds(false)->required(),
                Forms\Components\TimePicker::make('end_time')->seconds(false)->required(),
                Forms\Components\TextInput::make('grace_minutes')->numeric()->default(0)->required(),
                Forms\Components\TextInput::make('break_minutes')->numeric()->default(0)->required(),
                Forms\Components\TextInput::make('min_full_day_hours')->numeric()->default(8)->required(),
                Forms\Components\TextInput::make('min_half_day_hours')->numeric()->default(4)->required(),
                Forms\Components\TextInput::make('late_mark_after_minutes')->numeric()->default(0)->required(),
                Forms\Components\TextInput::make('early_going_before_minutes')->numeric()->default(0)->required(),
                Forms\Components\Toggle::make('is_active')->default(true)->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('start_time')->time('H:i'),
                Tables\Columns\TextColumn::make('end_time')->time('H:i'),
                Tables\Columns\TextColumn::make('min_full_day_hours')->label('Full Day Hrs'),
                Tables\Columns\TextColumn::make('min_half_day_hours')->label('Half Day Hrs'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}
