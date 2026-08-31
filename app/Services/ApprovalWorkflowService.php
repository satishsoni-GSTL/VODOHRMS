<?php

namespace App\Services;

use App\Contracts\Approvable;
use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use App\Notifications\ApprovalActionRequiredNotification;
use App\Notifications\ApprovalOutcomeNotification;
use App\Notifications\Concerns\NotifiesRecipients;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ApprovalWorkflowService
{
    use NotifiesRecipients;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Create an approval instance for a request and route it to level 1.
     */
    public function submit(Approvable&Model $requestable): ApprovalInstance
    {
        $definition = WorkflowDefinition::query()
            ->where('module', $requestable->getApprovalModule())
            ->where('is_active', true)
            ->firstOrFail();

        $applicableLevels = $this->applicableLevels($definition, $requestable->getApprovalConditionContext());

        if ($applicableLevels->isEmpty()) {
            throw ValidationException::withMessages([
                'workflow' => "No approval levels configured for module [{$requestable->getApprovalModule()}].",
            ]);
        }

        $instance = ApprovalInstance::create([
            'workflow_definition_id' => $definition->id,
            'requestable_type' => $requestable::class,
            'requestable_id' => $requestable->getKey(),
            'current_level' => 1,
            'status' => ApprovalInstance::STATUS_PENDING,
        ]);

        $requestable->update(['approval_instance_id' => $instance->id]);
        $requestable->applyApprovalOutcome('approved_level', null);

        $employee = Employee::find($requestable->getRequestingEmployeeId());

        if ($employee) {
            $this->notifyCurrentLevelApprovers($instance, $employee);
        }

        return $instance;
    }

    /**
     * @return Collection<int, WorkflowLevel>
     */
    public function applicableLevels(WorkflowDefinition $definition, array $context): Collection
    {
        return $definition->levels->filter(
            fn (WorkflowLevel $level) => $this->levelConditionMatches($level, $context)
        )->values();
    }

    private function levelConditionMatches(WorkflowLevel $level, array $context): bool
    {
        $rules = $level->condition_rules ?? [];

        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? '=';
            $value = $rule['value'] ?? null;

            if ($field === null || ! array_key_exists($field, $context)) {
                return false;
            }

            $actual = $context[$field];

            $matches = match ($operator) {
                '>' => $actual > $value,
                '>=' => $actual >= $value,
                '<' => $actual < $value,
                '<=' => $actual <= $value,
                '!=' => $actual != $value,
                default => $actual == $value,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    public function currentLevel(ApprovalInstance $instance): ?WorkflowLevel
    {
        /** @var Approvable&Model $requestable */
        $requestable = $instance->requestable;
        $definition = $instance->workflowDefinition;
        $levels = $this->applicableLevels($definition, $requestable->getApprovalConditionContext());

        return $levels->get($instance->current_level - 1);
    }

    /**
     * @return array<int>
     */
    public function resolveApproverUserIds(WorkflowLevel $level, Employee $requestingEmployee): array
    {
        $userIds = match ($level->approver_type) {
            WorkflowLevel::APPROVER_REPORTING_MANAGER => array_filter([
                $requestingEmployee->reportingManager?->user?->id,
            ]),
            WorkflowLevel::APPROVER_HR => $requestingEmployee->hrManager?->user
                ? [$requestingEmployee->hrManager->user->id]
                : $this->userIdsWithRole('HR Admin'),
            WorkflowLevel::APPROVER_FINANCE => $this->userIdsWithRole('Finance Admin'),
            WorkflowLevel::APPROVER_SPECIFIC_ROLE => $level->approver_role_id
                ? User::query()->whereHas('roles', fn ($q) => $q->where('id', $level->approver_role_id))->pluck('id')->all()
                : [],
            WorkflowLevel::APPROVER_SPECIFIC_USER => array_filter([$level->approver_user_id]),
            WorkflowLevel::APPROVER_DEPARTMENT_HEAD, WorkflowLevel::APPROVER_MANAGEMENT => $this->userIdsWithRole('HR Admin'),
            default => [],
        };

        // A request must never be stuck with nobody able to act on it — e.g. the employee has no
        // reporting manager configured yet, or nobody currently holds the HR/Finance role a level
        // requires. Fall back to Super Admin so there's always at least one approver.
        return $userIds !== [] ? $userIds : $this->userIdsWithRole('Super Admin');
    }

    private function userIdsWithRole(string $roleName): array
    {
        if (! Role::where('name', $roleName)->exists()) {
            return [];
        }

        return User::role($roleName)->pluck('id')->all();
    }

    public function canUserActOnInstance(ApprovalInstance $instance, User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $level = $this->currentLevel($instance);

        if (! $level) {
            return false;
        }

        $requestable = $instance->requestable;
        $employee = Employee::find($requestable->getRequestingEmployeeId());

        if (! $employee) {
            return false;
        }

        return in_array($user->id, $this->resolveApproverUserIds($level, $employee), true);
    }

    public function act(ApprovalInstance $instance, User $approver, string $action, ?string $remarks = null): void
    {
        if (! $this->canUserActOnInstance($instance, $approver)) {
            throw ValidationException::withMessages([
                'approval' => 'You are not authorized to act on this request at its current level.',
            ]);
        }

        if (in_array($action, [ApprovalAction::ACTION_REJECT, ApprovalAction::ACTION_SEND_BACK], true) && blank($remarks)) {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when rejecting or sending back a request.',
            ]);
        }

        DB::transaction(function () use ($instance, $approver, $action, $remarks) {
            $level = $this->currentLevel($instance);

            ApprovalAction::create([
                'approval_instance_id' => $instance->id,
                'level' => $instance->current_level,
                'approver_id' => $approver->id,
                'action' => $action,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            /** @var Approvable&Model $requestable */
            $requestable = $instance->requestable;
            $definition = $instance->workflowDefinition;
            $applicableLevels = $this->applicableLevels($definition, $requestable->getApprovalConditionContext());

            if ($action === ApprovalAction::ACTION_REJECT) {
                $instance->update(['status' => ApprovalInstance::STATUS_REJECTED]);
                $requestable->applyApprovalOutcome('rejected', $level);
            } elseif ($action === ApprovalAction::ACTION_SEND_BACK) {
                $instance->update(['status' => ApprovalInstance::STATUS_SENT_BACK, 'current_level' => 1]);
                $requestable->applyApprovalOutcome('sent_back', $level);
            } elseif ($action === ApprovalAction::ACTION_APPROVE) {
                $this->handleApprove($instance, $applicableLevels, $level, $requestable);
            }

            $this->auditLog->log(
                $action,
                $requestable,
                [],
                ['status' => $requestable->fresh()->status],
                reason: $remarks,
                module: $requestable->getApprovalModule(),
            );
        });

        $this->dispatchOutcomeNotifications($instance, $action, $remarks);
    }

    private function dispatchOutcomeNotifications(ApprovalInstance $instance, string $action, ?string $remarks): void
    {
        /** @var Approvable&Model $requestable */
        $requestable = $instance->requestable;
        $employee = Employee::find($requestable->getRequestingEmployeeId());

        if (! $employee) {
            return;
        }

        if ($action === ApprovalAction::ACTION_REJECT) {
            $this->notifyEmployee($employee, new ApprovalOutcomeNotification($requestable, 'rejected', $remarks));

            return;
        }

        if ($action === ApprovalAction::ACTION_SEND_BACK) {
            $this->notifyEmployee($employee, new ApprovalOutcomeNotification($requestable, 'sent_back', $remarks));
            $this->notifyCurrentLevelApprovers($instance, $employee);

            return;
        }

        if ($instance->status === ApprovalInstance::STATUS_APPROVED) {
            $this->notifyEmployee($employee, new ApprovalOutcomeNotification($requestable, 'approved'));

            return;
        }

        // Approved, but not the final level — the next level's approvers now need to act.
        $this->notifyCurrentLevelApprovers($instance, $employee);
    }

    private function notifyCurrentLevelApprovers(ApprovalInstance $instance, Employee $employee): void
    {
        $level = $this->currentLevel($instance);

        if (! $level) {
            return;
        }

        $users = User::query()->whereIn('id', $this->resolveApproverUserIds($level, $employee))->get();

        $this->notifyUsers($users, new ApprovalActionRequiredNotification($instance->requestable, $employee));
    }

    private function handleApprove(ApprovalInstance $instance, Collection $applicableLevels, ?WorkflowLevel $level, Approvable&Model $requestable): void
    {
        $isFinal = $instance->current_level >= $applicableLevels->count();

        if ($isFinal) {
            $instance->update(['status' => ApprovalInstance::STATUS_APPROVED]);
            $requestable->applyApprovalOutcome('approved', $level);

            return;
        }

        $instance->update(['current_level' => $instance->current_level + 1]);
        $requestable->applyApprovalOutcome('approved_level', $level);
    }
}
