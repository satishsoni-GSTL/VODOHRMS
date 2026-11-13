<?php

namespace App\Filament\Resources\HierarchyLevelResource\Pages;

use App\Filament\Resources\HierarchyLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHierarchyLevels extends ListRecords
{
    protected static string $resource = HierarchyLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...HierarchyLevelResource::listHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
