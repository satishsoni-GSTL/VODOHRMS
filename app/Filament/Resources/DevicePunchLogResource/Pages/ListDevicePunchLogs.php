<?php

namespace App\Filament\Resources\DevicePunchLogResource\Pages;

use App\Filament\Resources\DevicePunchLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDevicePunchLogs extends ListRecords
{
    protected static string $resource = DevicePunchLogResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }
}
