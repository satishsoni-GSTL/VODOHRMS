<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Holiday;
use App\Notifications\BirthdayNotification;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\UpcomingHolidayNotification;
use App\Notifications\WorkAnniversaryNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SendDailyHrReminders extends Command
{
    use NotifiesRecipients;

    protected $signature = 'hr:send-daily-reminders';

    protected $description = 'Email birthday, work-anniversary, and upcoming-holiday reminders for today.';

    private const ELIGIBLE_STATUSES = [
        Employee::STATUS_ACTIVE,
        Employee::STATUS_PROBATION,
        Employee::STATUS_NOTICE_PERIOD,
    ];

    public function handle(): int
    {
        $today = Carbon::today();

        $birthdaysSent = $this->sendBirthdayReminders($today);
        $anniversariesSent = $this->sendAnniversaryReminders($today);
        $holidaysSent = $this->sendHolidayReminders($today);

        $this->info("Birthdays: {$birthdaysSent}, Anniversaries: {$anniversariesSent}, Holiday reminders: {$holidaysSent}.");

        return self::SUCCESS;
    }

    private function sendBirthdayReminders(Carbon $today): int
    {
        $celebrants = Employee::query()
            ->whereNotNull('dob')
            ->whereMonth('dob', $today->month)
            ->whereDay('dob', $today->day)
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->get();

        foreach ($celebrants as $celebrant) {
            $notification = new BirthdayNotification($celebrant);

            foreach ($this->employeesInSameCompany($celebrant) as $employee) {
                $this->notifyEmployee($employee, $notification);
            }
        }

        return $celebrants->count();
    }

    private function sendAnniversaryReminders(Carbon $today): int
    {
        $celebrants = Employee::query()
            ->whereNotNull('date_of_joining')
            ->whereMonth('date_of_joining', $today->month)
            ->whereDay('date_of_joining', $today->day)
            ->whereYear('date_of_joining', '<', $today->year)
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->get();

        foreach ($celebrants as $celebrant) {
            $notification = new WorkAnniversaryNotification($celebrant);

            foreach ($this->employeesInSameCompany($celebrant) as $employee) {
                $this->notifyEmployee($employee, $notification);
            }
        }

        return $celebrants->count();
    }

    private function sendHolidayReminders(Carbon $today): int
    {
        $tomorrow = $today->copy()->addDay();

        $holidays = Holiday::query()->whereDate('date', $tomorrow->toDateString())->get();

        foreach ($holidays as $holiday) {
            $notification = new UpcomingHolidayNotification($holiday);

            foreach ($this->employeesForHolidayScope($holiday->company_id) as $employee) {
                $this->notifyEmployee($employee, $notification);
            }
        }

        return $holidays->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function employeesInSameCompany(Employee $celebrant)
    {
        return $this->activeEmployeesQuery()
            ->when(
                $celebrant->company_id,
                fn (Builder $query) => $query->where('company_id', $celebrant->company_id),
                fn (Builder $query) => $query->whereNull('company_id'),
            )
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function employeesForHolidayScope(?int $companyId)
    {
        return $this->activeEmployeesQuery()
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->get();
    }

    private function activeEmployeesQuery(): Builder
    {
        return Employee::query()->whereIn('status', self::ELIGIBLE_STATUSES);
    }
}
