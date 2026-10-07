<?php

namespace App\Filament\Resources\EmployeeLoanResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Resources\EmployeeLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeLoan extends EditRecord
{
    use LocksEmployeeToSelf;

    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function employeeOverridePermission(): string
    {
        return 'loan.manage';
    }
}
