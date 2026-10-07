<?php

namespace App\Filament\Resources\WorkFromHomeRequestResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Concerns\PrefillsFromReapply;
use App\Filament\Resources\WorkFromHomeRequestResource;
use App\Models\Employee;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkFromHomeRequest extends CreateRecord
{
    use PrefillsFromReapply;
    use LocksEmployeeToSelf;

    protected static string $resource = WorkFromHomeRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = Employee::findOrFail($data['employee_id']);

        return app(WorkFromHomeService::class)->request(
            $employee,
            Carbon::parse($data['from_date']),
            Carbon::parse($data['to_date']),
            $data['reason'],
        );
    }

    protected function reapplyFields(): array
    {
        return ['employee_id', 'from_date', 'to_date', 'reason'];
    }

    protected function employeeOverridePermission(): string
    {
        return 'attendance.manage';
    }
}
