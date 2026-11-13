<?php

namespace App\Filament\Resources\GeoLevelResource\Pages;

use App\Filament\Resources\GeoLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeoLevels extends ListRecords
{
    protected static string $resource = GeoLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...GeoLevelResource::listHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
