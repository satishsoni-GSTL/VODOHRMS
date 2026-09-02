<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use Illuminate\Database\Seeder;

class Phase2Seeder extends Seeder
{
    public function run(): void
    {
        // Single approval: the reporting manager, or HR via the module-manage override,
        // approves a regularization outright.
        $this->seedWorkflow(
            WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION,
            'Attendance Regularization Approval',
            [
                ['approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER],
            ]
        );

        $this->seedWorkflow(
            WorkflowDefinition::MODULE_LEAVE,
            'Leave Approval',
            [
                ['approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER],
            ]
        );

        $this->seedWorkflow(
            WorkflowDefinition::MODULE_WORK_FROM_HOME,
            'Work From Home Approval',
            [
                ['approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER],
            ]
        );

        Shift::firstOrCreate(
            ['name' => 'General Shift'],
            [
                'type' => 'general', 'start_time' => '09:30', 'end_time' => '18:30',
                'grace_minutes' => 15, 'break_minutes' => 60,
                'min_full_day_hours' => 8, 'min_half_day_hours' => 4,
                'late_mark_after_minutes' => 15, 'early_going_before_minutes' => 0,
                'is_active' => true,
            ]
        );

        $leaveTypes = [
            ['name' => 'Casual Leave', 'code' => 'CL', 'annual_entitlement' => 12, 'accrual_frequency' => 'monthly', 'max_days_per_request' => 3],
            ['name' => 'Sick Leave', 'code' => 'SL', 'annual_entitlement' => 12, 'accrual_frequency' => 'monthly'],
            ['name' => 'Earned Leave', 'code' => 'EL', 'annual_entitlement' => 15, 'accrual_frequency' => 'annual', 'carry_forward_allowed' => true, 'max_carry_forward' => 30, 'encashment_allowed' => true],
            ['name' => 'Leave Without Pay', 'code' => 'LWP', 'annual_entitlement' => 0, 'accrual_frequency' => 'none', 'allow_negative_balance' => true],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::firstOrCreate(['code' => $type['code']], $type + ['is_active' => true]);
        }
    }

    private function seedWorkflow(string $module, string $name, array $levels): void
    {
        $definition = WorkflowDefinition::firstOrCreate(
            ['module' => $module],
            ['name' => $name, 'is_active' => true]
        );

        if ($definition->levels()->exists()) {
            return;
        }

        foreach ($levels as $index => $level) {
            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => $index + 1,
                'approver_type' => $level['approver_type'],
                'approver_role_id' => $level['approver_role_id'] ?? null,
                'approver_user_id' => $level['approver_user_id'] ?? null,
                'condition_rules' => $level['condition_rules'] ?? null,
            ]);
        }
    }
}
