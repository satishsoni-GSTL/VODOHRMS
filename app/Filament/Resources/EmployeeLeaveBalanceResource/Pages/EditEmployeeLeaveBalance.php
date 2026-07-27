<?php

namespace App\Filament\Resources\EmployeeLeaveBalanceResource\Pages;

use App\Filament\Resources\EmployeeLeaveBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeLeaveBalance extends EditRecord
{
    protected static string $resource = EmployeeLeaveBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
