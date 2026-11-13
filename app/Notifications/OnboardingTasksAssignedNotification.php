<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\OnboardingTask;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

/** "You have N onboarding tasks for <new joiner>" — one message per owner, or an overdue reminder. */
class OnboardingTasksAssignedNotification extends BaseNotification
{
    /** @param  Collection<int, OnboardingTask>  $tasks */
    public function __construct(
        private readonly Employee $employee,
        private readonly Collection $tasks,
        private readonly bool $overdueReminder = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable);
    }

    private function name(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->employee->full_name));
    }

    public function toPush(object $notifiable): ?array
    {
        $n = $this->tasks->count();

        return [
            'title' => $this->overdueReminder ? 'Onboarding tasks overdue' : 'New onboarding tasks',
            'body' => "{$n} task".($n === 1 ? '' : 's')." for {$this->name()}".($this->overdueReminder ? ' are past due.' : '.'),
            'data' => ['screen' => 'home'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $joining = $this->employee->date_of_joining?->format('d M Y');

        $mail = (new MailMessage)
            ->subject(($this->overdueReminder ? 'Overdue onboarding tasks: ' : 'Onboarding tasks: ').$this->name())
            ->line($this->overdueReminder
                ? "These onboarding tasks for {$this->name()} ({$this->employee->employee_code}) are past their due date:"
                : "{$this->name()} ({$this->employee->employee_code}) joins on {$joining}. You have these onboarding tasks:");

        foreach ($this->tasks as $task) {
            $mail->line("• {$task->title} — due {$task->due_date->format('d M Y')}");
        }

        return $mail->action('Open Onboarding Tasks', url('/admin/onboarding-tasks'));
    }
}
