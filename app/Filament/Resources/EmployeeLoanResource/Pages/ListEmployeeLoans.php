<?php

namespace App\Filament\Resources\EmployeeLoanResource\Pages;

use App\Filament\Resources\EmployeeLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeLoans extends ListRecords
{
    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
