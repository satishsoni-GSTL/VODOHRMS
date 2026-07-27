<?php

namespace App\Filament\Resources\AttendanceRegularizationResource\Pages;

use App\Filament\Resources\AttendanceRegularizationResource;
use App\Models\Employee;
use App\Services\AttendanceRegularizationService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAttendanceRegularization extends CreateRecord
{
    protected static string $resource = AttendanceRegularizationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = Employee::findOrFail($data['employee_id']);

        return app(AttendanceRegularizationService::class)->request(
            $employee,
            Carbon::parse($data['attendance_date']),
            $data['request_type'],
            $data['requested_values'] ?? [],
            $data['reason'],
            $data['attachment_path'] ?? null,
        );
    }
}
