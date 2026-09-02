<?php

use App\Models\ApprovalInstance;
use App\Models\AttendanceRegularization;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use Illuminate\Database\Migrations\Migration;

/**
 * Attendance regularization now needs a single approval: whoever acts first — the reporting
 * manager, or HR via the module-manage override — approves it outright. Collapse the old
 * two-level (manager -> HR) chain to one manager level and pull any in-flight instance
 * that was sitting at level 2 back to level 1 so it isn't stranded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $definition = WorkflowDefinition::where('module', WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION)->first();

        if (! $definition) {
            return;
        }

        WorkflowLevel::where('workflow_definition_id', $definition->id)
            ->where('sequence', '>', 1)
            ->delete();

        WorkflowLevel::updateOrCreate(
            ['workflow_definition_id' => $definition->id, 'sequence' => 1],
            ['approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER, 'condition_rules' => null],
        );

        ApprovalInstance::where('requestable_type', AttendanceRegularization::class)
            ->where('status', ApprovalInstance::STATUS_PENDING)
            ->where('current_level', '>', 1)
            ->update(['current_level' => 1]);
    }

    public function down(): void
    {
        $definition = WorkflowDefinition::where('module', WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION)->first();

        if (! $definition) {
            return;
        }

        WorkflowLevel::updateOrCreate(
            ['workflow_definition_id' => $definition->id, 'sequence' => 2],
            ['approver_type' => WorkflowLevel::APPROVER_HR, 'condition_rules' => null],
        );
    }
};
