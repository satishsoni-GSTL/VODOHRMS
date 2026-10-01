<?php

namespace App\Filament\Resources\OptionalHolidayLimitResource\Pages;

use App\Filament\Resources\OptionalHolidayLimitResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOptionalHolidayLimits extends ManageRecords
{
    protected static string $resource = OptionalHolidayLimitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Set Limit'),
        ];
    }
}
