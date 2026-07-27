<?php

namespace App\Filament\Resources\PayrollInputResource\Pages;

use App\Filament\Resources\PayrollInputResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollInput extends CreateRecord
{
    protected static string $resource = PayrollInputResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
