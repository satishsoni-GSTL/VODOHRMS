<?php

use App\Models\ApprovalInstance;
use App\Models\AttendanceRegularization;
use App\Models\LeaveApplication;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use App\Models\WorkFromHomeRequest;
use Illuminate\Database\Migrations\Migration;

/**
 * Guarantee the leave / regularization / work-from-home approval workflows exist and are
 * active, then route any request that was raised while they didn't (approval_instance_id
 * is null) so it can actually be approved. Without this an environment that never ran
 * Phase2Seeder leaves every such request stuck with no approve/reject button.
 */
return new class extends Migration
{
    private const MODULES = [
        [WorkflowDefinition::MODULE_LEAVE, 'Leave Approval', LeaveApplication::class],
        [WorkflowDefinition::MODULE_ATTENDANCE_REGULARIZATION, 'Attendance Regularization Approval', AttendanceRegularization::class],
        [WorkflowDefinition::MODULE_WORK_FROM_HOME, 'Work From Home Approval', WorkFromHomeRequest::class],
    ];

    public function up(): void
    {
        foreach (self::MODULES as [$module, $name, $modelClass]) {
            $definition = WorkflowDefinition::firstOrCreate(
                ['module' => $module],
                ['name' => $name, 'is_active' => true],
            );

            if (! $definition->is_active) {
                $definition->update(['is_active' => true]);
            }

            if (! $definition->levels()->exists()) {
                WorkflowLevel::create([
                    'workflow_definition_id' => $definition->id,
                    'sequence' => 1,
                    'approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER,
                ]);
            }

            $this->routeOrphans($modelClass, $definition->id);
        }
    }

    public function down(): void
    {
        // No-op: we won't tear down workflows or un-route requests.
    }

    private function routeOrphans(string $modelClass, int $definitionId): void
    {
        $modelClass::query()
            ->whereNull('approval_instance_id')
            ->whereNotIn('status', [$modelClass::STATUS_APPROVED, $modelClass::STATUS_REJECTED])
            ->get()
            ->each(function ($request) use ($modelClass, $definitionId) {
                $instance = ApprovalInstance::create([
                    'workflow_definition_id' => $definitionId,
                    'requestable_type' => $modelClass,
                    'requestable_id' => $request->getKey(),
                    'current_level' => 1,
                    'status' => ApprovalInstance::STATUS_PENDING,
                ]);

                $request->update(['approval_instance_id' => $instance->id]);
                $request->applyApprovalOutcome('approved_level', null);
            });
    }
};
