<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\ProbationDueNotification;
use App\Services\ProbationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class SendProbationReminders extends Command
{
    use NotifiesRecipients;

    protected $signature = 'hr:send-probation-reminders';

    protected $description = 'Remind reporting managers and HR about probation reviews that are coming due or overdue.';

    /** Days before the probation end date on which a reminder goes out. */
    private const REMIND_DAYS_BEFORE = [30, 15, 7, 0];

    public function handle(ProbationService $probation): int
    {
        $today = Carbon::today();
        $sent = 0;

        foreach ($probation->dueEmployees(max(self::REMIND_DAYS_BEFORE)) as $employee) {
            $daysLeft = (int) $today->diffInDays($employee->probationEndDate(), false);

            // On the milestone days, then once a week while overdue.
            $due = in_array($daysLeft, self::REMIND_DAYS_BEFORE, true) || ($daysLeft < 0 && $daysLeft % 7 === 0);

            if (! $due || $probation->hasOpenReview($employee)) {
                continue;
            }

            $this->notifyUsers($this->recipients($employee), new ProbationDueNotification($employee));
            $sent++;
        }

        $this->info("Probation reminders sent for {$sent} employee(s).");

        return self::SUCCESS;
    }

    /** @return array<int, User|null> */
    private function recipients(Employee $employee): array
    {
        $hr = $employee->hrManager?->user
            ? [$employee->hrManager->user]
            : (Role::where('name', 'HR Admin')->exists() ? User::role('HR Admin')->get()->all() : []);

        return [$employee->reportingManager?->user, ...$hr];
    }
}
