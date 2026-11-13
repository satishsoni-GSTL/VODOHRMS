<?php

namespace App\Filament\Resources\GeographyResource\Pages;

use App\Filament\Resources\GeographyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeographies extends ListRecords
{
    protected static string $resource = GeographyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...GeographyResource::listHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
