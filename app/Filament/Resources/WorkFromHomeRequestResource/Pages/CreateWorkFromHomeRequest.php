<?php

namespace App\Filament\Resources\WorkFromHomeRequestResource\Pages;

use App\Filament\Resources\WorkFromHomeRequestResource;
use App\Models\Employee;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkFromHomeRequest extends CreateRecord
{
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
}
