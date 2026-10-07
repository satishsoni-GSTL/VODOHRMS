<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MobileDeviceResource\Pages;
use App\Models\MobileDevice;
use App\Services\MobileAuthService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Phones signed in to the employee mobile app. Revoking one signs that phone out; the
 * employee has to log in on it again.
 */
class MobileDeviceResource extends Resource
{
    protected static ?string $model = MobileDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Roles & Permissions';

    protected static ?string $navigationLabel = 'Mobile App Devices';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['Super Admin', 'HR Admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_used_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('device_name')->label('Device')->placeholder('—'),
                Tables\Columns\TextColumn::make('platform')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('app_version')->label('App')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Signed in')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')->label('Last used')->since()->sortable(),
                Tables\Columns\TextColumn::make('last_ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->state(fn (MobileDevice $record) => $record->isActive() ? 'Active' : 'Revoked')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label('Status')
                    ->nullable()
                    ->trueLabel('Revoked')
                    ->falseLabel('Active')
                    ->default(false),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Sign out device')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MobileDevice $record) => $record->isActive())
                    ->action(function (MobileDevice $record) {
                        app(MobileAuthService::class)->revoke($record);
                        Notification::make()->title('Device signed out')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('revokeSelected')
                    ->label('Sign out selected')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (MobileDevice $d) => $d->isActive() && app(MobileAuthService::class)->revoke($d))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileDevices::route('/'),
        ];
    }
}
