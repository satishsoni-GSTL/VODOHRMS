<?php

namespace App\Filament\Resources\EmployeeLoanResource\Pages;

use App\Filament\Resources\EmployeeLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeLoan extends EditRecord
{
    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
