<?php

namespace App\Filament\Resources\LeaveApplicationResource\Pages;

use App\Filament\Resources\LeaveApplicationResource;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveApplicationService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLeaveApplication extends CreateRecord
{
    protected static string $resource = LeaveApplicationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        return app(LeaveApplicationService::class)->apply(
            $employee,
            $leaveType,
            Carbon::parse($data['from_date']),
            Carbon::parse($data['to_date']),
            (bool) ($data['is_half_day'] ?? false),
            $data['half_day_session'] ?? null,
            $data['reason'] ?? null,
            $data['attachment_path'] ?? null,
        );
    }
}
