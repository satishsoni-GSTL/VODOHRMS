<?php

namespace App\Filament\Resources\GeographyResource\Pages;

use App\Filament\Resources\GeographyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeography extends EditRecord
{
    protected static string $resource = GeographyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
