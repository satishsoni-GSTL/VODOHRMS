<?php

namespace App\Console\Commands;

use App\Models\OnboardingTask;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\OnboardingTasksAssignedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class SendOnboardingReminders extends Command
{
    use NotifiesRecipients;

    protected $signature = 'hr:send-onboarding-reminders';

    protected $description = 'Remind owners about onboarding tasks that just went overdue (and every Monday, all overdue ones).';

    public function handle(): int
    {
        $today = Carbon::today();

        $tasks = OnboardingTask::query()
            ->with(['employee.user', 'role'])
            ->where('status', OnboardingTask::STATUS_PENDING)
            ->when(
                $today->isMonday(),
                fn ($q) => $q->whereDate('due_date', '<', $today),
                fn ($q) => $q->whereDate('due_date', $today->copy()->subDay()),
            )
            ->get();

        $byUserAndEmployee = [];

        foreach ($tasks as $task) {
            foreach ($this->owners($task) as $user) {
                $byUserAndEmployee[$user->id.'-'.$task->employee_id]['user'] = $user;
                $byUserAndEmployee[$user->id.'-'.$task->employee_id]['tasks'][] = $task;
            }
        }

        foreach ($byUserAndEmployee as ['user' => $user, 'tasks' => $userTasks]) {
            $this->notifyUsers([$user], new OnboardingTasksAssignedNotification($userTasks[0]->employee, collect($userTasks), overdueReminder: true));
        }

        $this->info('Overdue onboarding reminders sent: '.count($byUserAndEmployee).'.');

        return self::SUCCESS;
    }

    /** @return iterable<int, User> */
    private function owners(OnboardingTask $task): iterable
    {
        if ($task->assigned_user_id) {
            return User::whereKey($task->assigned_user_id)->get();
        }

        if ($task->role) {
            return User::role($task->role->name)->get();
        }

        // A joiner's own task created before they had a login.
        return array_filter([$task->employee?->user]);
    }
}
