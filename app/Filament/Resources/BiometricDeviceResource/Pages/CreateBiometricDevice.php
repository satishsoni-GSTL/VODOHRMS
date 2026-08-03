<?php

namespace App\Filament\Resources\BiometricDeviceResource\Pages;

use App\Filament\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateBiometricDevice extends CreateRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    private string $plainToken;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->plainToken = Str::random(40);
        $data['api_token_hash'] = BiometricDevice::hashToken($this->plainToken);

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Device registered')
            ->body("Copy this API token now for the device agent config — it will not be shown again:\n{$this->plainToken}")
            ->success()
            ->persistent();
    }
}
