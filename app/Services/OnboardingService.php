<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTask;
use App\Models\OnboardingTaskTemplate;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\OnboardingTasksAssignedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class OnboardingService
{
    use NotifiesRecipients;

    /** New joiners get onboarding tasks automatically only if they joined within this many days. */
    public const AUTO_GENERATE_WITHIN_DAYS = 30;

    /**
     * Recompute every checklist flag from the employee's actual current data, rather than
     * trusting manually-ticked checkboxes that can drift out of sync with reality.
     */
    public function refresh(Employee $employee): OnboardingChecklist
    {
        $checklist = OnboardingChecklist::firstOrCreate(['employee_id' => $employee->id]);

        $checklist->personal_details_done = filled($employee->first_name) && filled($employee->dob) && filled($employee->personal_mobile);
        $checklist->documents_done = $employee->documents()->exists();
        $checklist->statutory_done = filled($employee->statutoryDetail?->pan) && filled($employee->statutoryDetail?->uan);
        $checklist->bank_done = $employee->bankDetails()->exists();
        $checklist->department_done = filled($employee->department_id) && filled($employee->designation_id) && filled($employee->reporting_manager_id);
        $checklist->salary_done = $employee->currentSalaryStructure() !== null;
        $checklist->login_done = $employee->user()->exists();
        $checklist->asset_allocation_done = $employee->assets()->exists();

        $checklist->recalculateCompletion();
        $checklist->save();

        return $checklist;
    }

    /** Called when an employee is created: start onboarding for recent / upcoming joiners only. */
    public function startForNewJoiner(Employee $employee): void
    {
        if (! $employee->date_of_joining || $employee->date_of_joining->lt(today()->subDays(self::AUTO_GENERATE_WITHIN_DAYS))) {
            return;
        }

        $this->refresh($employee);
        $this->generateTasks($employee);
    }

    /**
     * Create the employee's onboarding tasks from the active templates (company-specific
     * templates apply only to that company) and tell each owner what they have to do.
     *
     * @return Collection<int, OnboardingTask>
     */
    public function generateTasks(Employee $employee): Collection
    {
        if (! $employee->date_of_joining) {
            throw ValidationException::withMessages(['employee_id' => 'Set the date of joining before starting onboarding.']);
        }

        if ($employee->onboardingTasks()->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Onboarding tasks already exist for this employee.']);
        }

        $templates = OnboardingTaskTemplate::query()
            ->active()
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->orderBy('sequence')
            ->get();

        $tasks = DB::transaction(fn () => $templates->map(fn (OnboardingTaskTemplate $template) => OnboardingTask::create([
            'employee_id' => $employee->id,
            'template_id' => $template->id,
            'title' => $template->title,
            'description' => $template->description,
            'assignee_type' => $template->assignee_type,
            'due_date' => $employee->date_of_joining->copy()->addDays($template->due_offset_days)->toDateString(),
            'status' => OnboardingTask::STATUS_PENDING,
            'sequence' => $template->sequence,
        ] + $this->resolveOwner($template, $employee))));

        $this->notifyOwners($employee, $tasks);

        return $tasks;
    }

    /** @return array{assigned_user_id: int|null, role_id: int|null} */
    private function resolveOwner(OnboardingTaskTemplate $template, Employee $employee): array
    {
        $hr = fn () => $employee->hrManager?->user
            ? ['assigned_user_id' => $employee->hrManager->user->id, 'role_id' => null]
            : ['assigned_user_id' => null, 'role_id' => Role::where('name', 'HR Admin')->value('id')];

        return match ($template->assignee_type) {
            OnboardingTaskTemplate::ASSIGNEE_MANAGER => $employee->reportingManager?->user
                ? ['assigned_user_id' => $employee->reportingManager->user->id, 'role_id' => null]
                : $hr(), // no manager yet: HR covers it
            // The joiner may not have a login yet; scopeAssignedTo also matches on employee_id.
            OnboardingTaskTemplate::ASSIGNEE_EMPLOYEE => ['assigned_user_id' => $employee->user?->id, 'role_id' => null],
            OnboardingTaskTemplate::ASSIGNEE_ROLE => ['assigned_user_id' => null, 'role_id' => $template->role_id],
            OnboardingTaskTemplate::ASSIGNEE_USER => $template->user_id
                ? ['assigned_user_id' => $template->user_id, 'role_id' => null]
                : $hr(),
            default => $hr(),
        };
    }

    public function complete(OnboardingTask $task, User $by, ?string $remarks = null): void
    {
        $this->close($task, OnboardingTask::STATUS_DONE, $by, $remarks);
    }

    public function skip(OnboardingTask $task, User $by, string $remarks): void
    {
        if (blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Say why this task is being skipped.']);
        }

        $this->close($task, OnboardingTask::STATUS_SKIPPED, $by, $remarks);
    }

    public function reopen(OnboardingTask $task): void
    {
        $task->update(['status' => OnboardingTask::STATUS_PENDING, 'completed_by' => null, 'completed_at' => null]);
    }

    private function close(OnboardingTask $task, string $status, User $by, ?string $remarks): void
    {
        if ($task->status !== OnboardingTask::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'This task is already closed.']);
        }

        $task->update([
            'status' => $status,
            'completed_by' => $by->id,
            'completed_at' => now(),
            'remarks' => $remarks,
        ]);
    }

    /** Whether the user may tick off this task: its owner, the joiner for their own task, or HR. */
    public function canAct(OnboardingTask $task, User $user): bool
    {
        return $user->can('onboarding.manage')
            || $task->isAssignedTo($user)
            || ($task->assignee_type === OnboardingTaskTemplate::ASSIGNEE_EMPLOYEE && $task->employee_id === $user->employee_id);
    }

    /** @param  Collection<int, OnboardingTask>  $tasks */
    private function notifyOwners(Employee $employee, Collection $tasks): void
    {
        $byUser = [];

        foreach ($tasks as $task) {
            $users = $task->assigned_user_id
                ? User::whereKey($task->assigned_user_id)->get()
                : (($role = $task->role_id ? Role::find($task->role_id) : null) ? User::role($role->name)->get() : collect());

            foreach ($users as $user) {
                $byUser[$user->id]['user'] = $user;
                $byUser[$user->id]['tasks'][] = $task;
            }
        }

        foreach ($byUser as ['user' => $user, 'tasks' => $userTasks]) {
            $this->notifyUsers([$user], new OnboardingTasksAssignedNotification($employee, collect($userTasks)));
        }
    }
}
