<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use Carbon\CarbonInterface;

class AttendanceRegularizationService
{
    public function __construct(private readonly ApprovalWorkflowService $workflow) {}

    public function request(
        Employee $employee,
        CarbonInterface $date,
        string $requestType,
        array $requestedValues,
        string $reason,
        ?string $attachmentPath,
    ): AttendanceRegularization {
        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $date->toDateString())
            ->first();

        $regularization = AttendanceRegularization::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'request_type' => $requestType,
            'old_values' => $existing?->only(['first_in', 'last_out', 'status']),
            'requested_values' => $requestedValues,
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => AttendanceRegularization::STATUS_PENDING,
        ]);

        $this->workflow->submit($regularization);

        return $regularization->fresh();
    }
}
