<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Birthdays and work anniversaries across the whole company (not just the viewer's team),
 * for the web dashboard and the mobile app home screen. Same audience rules as the daily
 * e-mail reminders (SendDailyHrReminders): active / probation / notice-period employees of
 * the viewer's company. Only the day is shared — never the birth year or age.
 */
class CelebrationService
{
    public const TYPE_BIRTHDAY = 'birthday';

    public const TYPE_ANNIVERSARY = 'anniversary';

    private const ELIGIBLE_STATUSES = [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD];

    /**
     * Celebrations from today through the next $days days, today's first.
     *
     * @return Collection<int, array{type: string, date: string, is_today: bool, days_away: int, years: ?int, employee: Employee, is_me: bool, is_my_team: bool}>
     */
    public function upcoming(?Employee $viewer, int $days = 7, ?CarbonInterface $today = null): Collection
    {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $until = $today->copy()->addDays($days);
        $teamIds = $viewer ? [$viewer->id, ...$viewer->allSubordinateIds(), $viewer->reporting_manager_id] : [];

        $employees = Employee::query()
            ->with(['designation:id,name', 'department:id,name'])
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->when(
                $viewer?->company_id,
                fn ($q) => $q->where('company_id', $viewer->company_id),
            )
            ->where(fn ($q) => $q->whereNotNull('dob')->orWhereNotNull('date_of_joining'))
            ->get();

        $items = collect();

        foreach ($employees as $employee) {
            if ($employee->dob && ($date = $this->nextOccurrence($employee->dob, $today))->lte($until)) {
                $items->push($this->item(self::TYPE_BIRTHDAY, $date, $today, null, $employee, $viewer, $teamIds));
            }

            if ($employee->date_of_joining) {
                $date = $this->nextOccurrence($employee->date_of_joining, $today);
                $years = $date->year - $employee->date_of_joining->year;

                // Joining day itself isn't an anniversary.
                if ($years >= 1 && $date->lte($until)) {
                    $items->push($this->item(self::TYPE_ANNIVERSARY, $date, $today, $years, $employee, $viewer, $teamIds));
                }
            }
        }

        return $items
            ->sortBy([['days_away', 'asc'], ['type', 'desc'], [fn ($a, $b) => strcmp($a['employee']->first_name, $b['employee']->first_name)]])
            ->values();
    }

    /** This year's (or next year's, if already passed) occurrence; 29 Feb → 28 Feb in non-leap years. */
    private function nextOccurrence(CarbonInterface $original, Carbon $today): Carbon
    {
        $make = function (int $year) use ($original) {
            $day = $original->month === 2 && $original->day === 29 && ! Carbon::create($year)->isLeapYear() ? 28 : $original->day;

            return Carbon::create($year, $original->month, $day)->startOfDay();
        };

        $date = $make($today->year);

        return $date->lt($today) ? $make($today->year + 1) : $date;
    }

    /** @param  array<int, int|null>  $teamIds */
    private function item(string $type, Carbon $date, Carbon $today, ?int $years, Employee $employee, ?Employee $viewer, array $teamIds): array
    {
        return [
            'type' => $type,
            'date' => $date->toDateString(),
            'is_today' => $date->equalTo($today),
            'days_away' => (int) $today->diffInDays($date),
            'years' => $years,
            'employee' => $employee,
            'is_me' => $viewer?->id === $employee->id,
            'is_my_team' => in_array($employee->id, $teamIds, true),
        ];
    }
}
