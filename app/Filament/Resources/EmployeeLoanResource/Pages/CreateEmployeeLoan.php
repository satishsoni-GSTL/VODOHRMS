<?php

namespace App\Filament\Resources\EmployeeLoanResource\Pages;

use App\Filament\Resources\EmployeeLoanResource;
use App\Models\Employee;
use App\Services\LoanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployeeLoan extends CreateRecord
{
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
}
