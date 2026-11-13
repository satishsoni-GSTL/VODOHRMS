<?php

namespace App\Filament\Resources\ExpenseClaimResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Resources\ExpenseClaimResource;
use App\Models\Employee;
use App\Services\ExpenseClaimService;
use App\Services\TravelService;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExpenseClaim extends CreateRecord
{
    use LocksEmployeeToSelf;

    protected static string $resource = ExpenseClaimResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = Employee::findOrFail($data['employee_id']);

        return DB::transaction(function () use ($employee, $data) {
            $claim = app(ExpenseClaimService::class)->submit(
                $employee,
                $data['claim_date'],
                $data['project_client'] ?? null,
                $data['lines'] ?? [],
            );

            app(TravelService::class)->linkClaim($claim, isset($data['travel_request_id']) ? (int) $data['travel_request_id'] : null);

            return $claim;
        });
    }

    protected function employeeOverridePermission(): string
    {
        return 'expense.manage';
    }
}
