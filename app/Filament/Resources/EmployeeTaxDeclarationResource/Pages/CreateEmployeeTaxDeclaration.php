<?php

namespace App\Filament\Resources\EmployeeTaxDeclarationResource\Pages;

use App\Filament\Resources\EmployeeTaxDeclarationResource;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\TaxSection;
use App\Services\TaxDeclarationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployeeTaxDeclaration extends CreateRecord
{
    protected static string $resource = EmployeeTaxDeclarationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TaxDeclarationService::class)->declare(
            Employee::findOrFail($data['employee_id']),
            FinancialYear::findOrFail($data['financial_year_id']),
            TaxSection::findOrFail($data['tax_section_id']),
            (float) $data['declared_amount'],
            $data['proof_path'] ?? null,
        );
    }
}
