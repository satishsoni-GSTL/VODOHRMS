<?php

namespace App\Filament\Resources\AttendanceRegularizationResource\Pages;

use App\Filament\Concerns\PrefillsFromReapply;
use App\Filament\Resources\AttendanceRegularizationResource;
use App\Models\Employee;
use App\Services\AttendanceRegularizationService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAttendanceRegularization extends CreateRecord
{
    use PrefillsFromReapply;

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

    protected function reapplyFields(): array
    {
        return ['employee_id', 'attendance_date', 'request_type', 'requested_values', 'reason', 'attachment_path'];
    }
}
