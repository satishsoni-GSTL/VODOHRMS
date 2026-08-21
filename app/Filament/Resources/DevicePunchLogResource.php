<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevicePunchLogResource\Pages;
use App\Models\DevicePunchLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DevicePunchLogResource extends Resource
{
    protected static ?string $model = DevicePunchLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Biometric Punch Logs';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.name')->label('Device')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('device_user_id')->label('Device User ID')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Matched Employee')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('punch_time')->dateTime('d M Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('punch_type')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => DevicePunchLog::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        DevicePunchLog::STATUS_MATCHED => 'success',
                        DevicePunchLog::STATUS_UNMATCHED => 'danger',
                        DevicePunchLog::STATUS_DUPLICATE => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Received At')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(DevicePunchLog::STATUSES),
                Tables\Filters\SelectFilter::make('biometric_device_id')->relationship('device', 'name')->label('Device'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevicePunchLogs::route('/'),
        ];
    }
}
