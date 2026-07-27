<?php

namespace App\Filament\Resources\EmployeeTaxRegimeResource\Pages;

use App\Filament\Resources\EmployeeTaxRegimeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTaxRegimes extends ListRecords
{
    protected static string $resource = EmployeeTaxRegimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
