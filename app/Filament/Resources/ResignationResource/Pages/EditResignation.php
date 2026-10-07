<?php

namespace App\Filament\Resources\ResignationResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Resources\ResignationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResignation extends EditRecord
{
    use LocksEmployeeToSelf;

    protected static string $resource = ResignationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function employeeOverridePermission(): string
    {
        return 'resignation.manage';
    }
}
