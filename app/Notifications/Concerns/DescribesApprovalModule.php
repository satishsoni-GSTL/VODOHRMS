<?php

namespace App\Notifications\Concerns;

use App\Models\WorkflowDefinition;

trait DescribesApprovalModule
{
    private function moduleLabel(string $module): string
    {
        return match ($module) {
            WorkflowDefinition::MODULE_LEAVE => 'Leave',
            WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION => 'Attendance Regularization',
            WorkflowDefinition::MODULE_EXPENSE => 'Expense Claim',
            WorkflowDefinition::MODULE_LOAN => 'Loan/Advance',
            WorkflowDefinition::MODULE_RESIGNATION => 'Resignation',
            WorkflowDefinition::MODULE_WORK_FROM_HOME => 'Work From Home',
            default => ucwords(str_replace('_', ' ', $module)),
        };
    }

    private function moduleRoute(string $module): string
    {
        return match ($module) {
            WorkflowDefinition::MODULE_LEAVE => '/admin/leave-applications',
            WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION => '/admin/attendance-regularizations',
            WorkflowDefinition::MODULE_EXPENSE => '/admin/expense-claims',
            WorkflowDefinition::MODULE_LOAN => '/admin/employee-loans',
            WorkflowDefinition::MODULE_RESIGNATION => '/admin/resignations',
            WorkflowDefinition::MODULE_WORK_FROM_HOME => '/admin/work-from-home-requests',
            default => '/admin',
        };
    }
}
