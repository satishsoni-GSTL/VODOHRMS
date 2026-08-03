<?php

namespace App\Filament\Resources\BiometricDeviceResource\Pages;

use App\Filament\Resources\BiometricDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBiometricDevices extends ListRecords
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
