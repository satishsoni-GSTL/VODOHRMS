<?php

namespace App\Filament\Resources\BiometricDeviceResource\Pages;

use App\Filament\Resources\BiometricDeviceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBiometricDevice extends EditRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('regenerateToken')
                ->label('Regenerate Token')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('The current token will stop working immediately. Update the device agent config with the new token.')
                ->action(function () {
                    $token = $this->record->issueToken();

                    Notification::make()
                        ->title('Token regenerated')
                        ->body("Copy this API token now for the device agent config — it will not be shown again:\n{$token}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
