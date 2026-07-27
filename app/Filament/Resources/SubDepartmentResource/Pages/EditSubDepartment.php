<?php

namespace App\Filament\Resources\SubDepartmentResource\Pages;

use App\Filament\Resources\SubDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubDepartment extends EditRecord
{
    protected static string $resource = SubDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
