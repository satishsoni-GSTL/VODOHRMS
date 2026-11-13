<?php

namespace App\Filament\Resources\GeoLevelResource\Pages;

use App\Filament\Resources\GeoLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeoLevel extends EditRecord
{
    protected static string $resource = GeoLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
