<?php

namespace App\Filament\Resources\EmployeeTaxDeclarationResource\Pages;

use App\Filament\Resources\EmployeeTaxDeclarationResource;
use App\Models\EmployeeTaxDeclaration;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeTaxDeclaration extends EditRecord
{
    protected static string $resource = EmployeeTaxDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Re-editing a rejected declaration resubmits it for verification.
        if ($this->record->status === EmployeeTaxDeclaration::STATUS_REJECTED) {
            $data['status'] = EmployeeTaxDeclaration::STATUS_DECLARED;
            $data['hr_remarks'] = null;
            $data['approved_amount'] = null;
            $data['rejected_amount'] = null;
            $data['eligible_amount'] = null;
        }

        return $data;
    }
}
