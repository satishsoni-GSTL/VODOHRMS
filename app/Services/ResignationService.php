<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExitClearance;
use App\Models\Resignation;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\ExitClearanceAssignedNotification;
use Spatie\Permission\Models\Role;

class ResignationService
{
    use NotifiesRecipients;

    public function __construct(private readonly ApprovalWorkflowService $workflow) {}

    public function submit(
        Employee $employee,
        string $resignationDate,
        string $reason,
        string $requestedLastWorkingDate,
    ): Resignation {
        $resignation = Resignation::create([
            'employee_id' => $employee->id,
            'resignation_date' => $resignationDate,
            'reason' => $reason,
            'requested_last_working_date' => $requestedLastWorkingDate,
            'notice_period_days' => $employee->notice_period_days,
            'status' => Resignation::STATUS_PENDING,
        ]);

        foreach (ExitClearance::DEPARTMENTS as $department) {
            $clearance = $resignation->exitClearances()->create([
                'department' => $department,
                'status' => ExitClearance::STATUS_PENDING,
            ]);

            $this->notifyUsers(
                $this->departmentOwners($department, $employee),
                new ExitClearanceAssignedNotification($clearance),
            );
        }

        $this->workflow->submit($resignation);

        return $resignation->fresh(['exitClearances']);
    }

    /**
     * @return array<int, User|null>
     */
    private function departmentOwners(string $department, Employee $employee): array
    {
        return match ($department) {
            'manager' => [$employee->reportingManager?->user],
            'finance' => $this->usersWithRole('Finance Admin'),
            // No dedicated IT/Admin role exists yet — HR Admin is the fallback owner.
            default => $this->usersWithRole('HR Admin'),
        };
    }

    /**
     * @return array<int, User>
     */
    private function usersWithRole(string $roleName): array
    {
        if (! Role::where('name', $roleName)->exists()) {
            return [];
        }

        return User::role($roleName)->get()->all();
    }
}
