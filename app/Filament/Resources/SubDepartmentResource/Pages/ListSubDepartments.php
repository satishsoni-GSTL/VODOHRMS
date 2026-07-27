<?php

namespace App\Filament\Resources\SubDepartmentResource\Pages;

use App\Filament\Resources\SubDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubDepartments extends ListRecords
{
    protected static string $resource = SubDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
