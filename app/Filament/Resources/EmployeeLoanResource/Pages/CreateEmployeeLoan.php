<?php

namespace App\Filament\Resources\EmployeeLoanResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Concerns\PrefillsFromReapply;
use App\Filament\Resources\EmployeeLoanResource;
use App\Models\Employee;
use App\Services\LoanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployeeLoan extends CreateRecord
{
    use PrefillsFromReapply;
    use LocksEmployeeToSelf;

    protected static string $resource = EmployeeLoanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(LoanService::class)->request(
            Employee::findOrFail($data['employee_id']),
            $data['type'],
            (float) $data['requested_amount'],
            $data['reason'],
            $data['request_date'],
        );
    }

    protected function reapplyFields(): array
    {
        return ['employee_id', 'type', 'requested_amount', 'reason'];
    }

    protected function employeeOverridePermission(): string
    {
        return 'loan.manage';
    }
}
