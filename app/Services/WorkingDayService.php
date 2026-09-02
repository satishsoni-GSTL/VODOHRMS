<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Single source of truth for "which calendar days in this range count as working days" —
 * i.e. not one of the employee's configured weekly-off days and not a holiday for their
 * company. Used for leave day counting and Work From Home.
 *
 * Only weekly-off days actually configured on the employee are excluded; an employee with
 * none set is treated as working every day. (Payroll LOP is separate — it assumes a
 * Sat/Sun default so unconfigured records aren't over-penalised.)
 */
class WorkingDayService
{
    /**
     * @return array<int, string> Y-m-d working days between $from and $to inclusive.
     */
    public function between(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $weeklyOff = collect($employee->weekly_off ?: [])
            ->map(fn ($d) => strtolower($d))
            ->all();

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            if (in_array(strtolower($date->format('l')), $weeklyOff, true)) {
                continue;
            }

            if (in_array($date->toDateString(), $holidays, true)) {
                continue;
            }

            $days[] = $date->toDateString();
        }

        return $days;
    }

    public function count(Employee $employee, CarbonInterface $from, CarbonInterface $to): int
    {
        return count($this->between($employee, $from, $to));
    }
}
