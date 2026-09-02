<?php

namespace App\Filament\Resources\EmployeeSalaryStructureResource\Pages;

use App\Filament\Resources\EmployeeSalaryStructureResource;
use App\Models\Employee;
use App\Services\SalaryStructureService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployeeSalaryStructure extends CreateRecord
{
    protected static string $resource = EmployeeSalaryStructureResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = Employee::findOrFail($data['employee_id']);

        $earningAmounts = collect($data['lines'] ?? [])
            ->mapWithKeys(fn (array $line) => [$line['salary_component_id'] => (float) $line['monthly_amount']])
            ->all();

        $deductionAmounts = collect($data['deduction_lines'] ?? [])
            ->mapWithKeys(fn (array $line) => [$line['salary_component_id'] => (float) $line['monthly_amount']])
            ->all();

        return app(SalaryStructureService::class)->assign(
            $employee,
            $data['effective_from'],
            (float) $data['annual_ctc'],
            $earningAmounts,
            auth()->id(),
            $data['remarks'] ?? null,
            $deductionAmounts,
        );
    }
}
