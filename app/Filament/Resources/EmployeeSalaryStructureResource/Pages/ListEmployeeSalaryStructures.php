<?php

namespace App\Filament\Resources\EmployeeSalaryStructureResource\Pages;

use App\Filament\Resources\EmployeeSalaryStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeSalaryStructures extends ListRecords
{
    protected static string $resource = EmployeeSalaryStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
