<?php

namespace App\Filament\Resources\PayrollInputResource\Pages;

use App\Filament\Resources\PayrollInputResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollInput extends EditRecord
{
    protected static string $resource = PayrollInputResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
