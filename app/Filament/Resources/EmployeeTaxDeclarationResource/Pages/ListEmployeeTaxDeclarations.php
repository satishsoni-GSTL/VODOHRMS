<?php

namespace App\Filament\Resources\EmployeeTaxDeclarationResource\Pages;

use App\Filament\Resources\EmployeeTaxDeclarationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTaxDeclarations extends ListRecords
{
    protected static string $resource = EmployeeTaxDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
