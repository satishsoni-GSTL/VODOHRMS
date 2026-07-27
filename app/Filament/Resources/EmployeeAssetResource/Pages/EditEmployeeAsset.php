<?php

namespace App\Filament\Resources\EmployeeAssetResource\Pages;

use App\Filament\Resources\EmployeeAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAsset extends EditRecord
{
    protected static string $resource = EmployeeAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
