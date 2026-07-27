<?php

namespace App\Filament\Resources\EmployeeTaxRegimeResource\Pages;

use App\Filament\Resources\EmployeeTaxRegimeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeTaxRegime extends CreateRecord
{
    protected static string $resource = EmployeeTaxRegimeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['selection_date'] = now()->toDateString();
        $data['changed_by'] = auth()->id();

        return $data;
    }
}
