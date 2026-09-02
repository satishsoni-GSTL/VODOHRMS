<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LeaveApplicationService
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflow,
        private readonly WorkingDayService $workingDays,
    ) {}

    /**
     * Leave is counted in working days only — the employee's weekly-offs and company
     * holidays inside the range don't consume balance.
     */
    public function calculateDays(Employee $employee, CarbonInterface $from, CarbonInterface $to, bool $isHalfDay): float
    {
        if ($isHalfDay) {
            return 0.5;
        }

        return (float) $this->workingDays->count($employee, $from, $to);
    }

    public function apply(
        Employee $employee,
        LeaveType $leaveType,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $isHalfDay,
        ?string $halfDaySession,
        ?string $reason,
        ?string $attachmentPath,
    ): LeaveApplication {
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['to_date' => 'To date cannot be before from date.']);
        }

        $days = $this->calculateDays($employee, $from, $to, $isHalfDay);

        if ($days <= 0) {
            throw ValidationException::withMessages(['from_date' => 'The selected range has no working days (all weekly-offs/holidays).']);
        }

        if ($leaveType->min_days_per_request && $days < $leaveType->min_days_per_request) {
            throw ValidationException::withMessages(['days' => "Minimum {$leaveType->min_days_per_request} day(s) required for {$leaveType->name}."]);
        }

        if ($leaveType->max_days_per_request && $days > $leaveType->max_days_per_request) {
            throw ValidationException::withMessages(['days' => "Maximum {$leaveType->max_days_per_request} day(s) allowed for {$leaveType->name}."]);
        }

        if ($leaveType->attachment_required && blank($attachmentPath)) {
            throw ValidationException::withMessages(['attachment_path' => "An attachment is required for {$leaveType->name}."]);
        }

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'days' => $days,
            'is_half_day' => $isHalfDay,
            'half_day_session' => $halfDaySession,
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => LeaveApplication::STATUS_DRAFT,
        ]);

        $this->workflow->submit($application);

        return $application->fresh();
    }
}
