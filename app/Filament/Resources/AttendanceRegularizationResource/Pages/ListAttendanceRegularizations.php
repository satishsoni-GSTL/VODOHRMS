<?php

namespace App\Filament\Resources\AttendanceRegularizationResource\Pages;

use App\Filament\Resources\AttendanceRegularizationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceRegularizations extends ListRecords
{
    protected static string $resource = AttendanceRegularizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
