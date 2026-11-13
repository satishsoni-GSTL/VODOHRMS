<?php

namespace App\Filament\Resources\HierarchyLevelResource\Pages;

use App\Filament\Resources\HierarchyLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHierarchyLevel extends EditRecord
{
    protected static string $resource = HierarchyLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
