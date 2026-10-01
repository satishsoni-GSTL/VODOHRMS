<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OptionalHolidayClaim;
use App\Models\OptionalHolidayLimit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Optional holidays are set up in the holiday calendar (type "optional") but are a working
 * day by default — an employee takes one off only by claiming it, up to a yearly limit.
 * The limit is configured per calendar year, with an optional per-employee override
 * (see OptionalHolidayLimit). Once claimed, the day counts as a holiday for that employee
 * everywhere holidays are used (working days, attendance register, payroll paid days).
 */
class OptionalHolidayService
{
    /**
     * The employee's own limit for the year if set, else the year's default, else 0.
     */
    public function limitFor(Employee $employee, int $year): int
    {
        $limits = OptionalHolidayLimit::query()
            ->where('year', $year)
            ->where(fn ($q) => $q->whereNull('employee_id')->orWhere('employee_id', $employee->id))
            ->get();

        $limit = $limits->firstWhere('employee_id', $employee->id) ?? $limits->firstWhere('employee_id', null);

        return (int) ($limit?->max_claims ?? 0);
    }

    public function usedFor(Employee $employee, int $year): int
    {
        return OptionalHolidayClaim::query()
            ->where('employee_id', $employee->id)
            ->where('status', OptionalHolidayClaim::STATUS_CLAIMED)
            ->whereHas('holiday', fn ($q) => $q->whereYear('date', $year))
            ->count();
    }

    /**
     * Upcoming optional holidays (today onwards) for the employee's company that they haven't claimed yet.
     *
     * @return Collection<int, Holiday>
     */
    public function claimableHolidays(Employee $employee): Collection
    {
        return Holiday::query()
            ->where('type', Holiday::TYPE_OPTIONAL)
            ->where('date', '>=', Carbon::today()->toDateString())
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->whereDoesntHave('claims', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', OptionalHolidayClaim::STATUS_CLAIMED))
            ->orderBy('date')
            ->get();
    }

    public function claim(Employee $employee, Holiday $holiday, ?string $reason = null, ?User $by = null): OptionalHolidayClaim
    {
        if (! $holiday->isOptional()) {
            throw ValidationException::withMessages(['holiday_id' => 'Only optional holidays can be claimed.']);
        }

        if ($holiday->company_id !== null && (int) $holiday->company_id !== (int) $employee->company_id) {
            throw ValidationException::withMessages(['holiday_id' => 'This optional holiday is not available for your company.']);
        }

        if ($holiday->date->lt(Carbon::today())) {
            throw ValidationException::withMessages(['holiday_id' => 'A past optional holiday cannot be claimed.']);
        }

        $existing = OptionalHolidayClaim::query()
            ->where('employee_id', $employee->id)
            ->where('holiday_id', $holiday->id)
            ->first();

        if ($existing?->status === OptionalHolidayClaim::STATUS_CLAIMED) {
            throw ValidationException::withMessages(['holiday_id' => 'This optional holiday is already claimed.']);
        }

        $year = (int) $holiday->date->format('Y');
        $limit = $this->limitFor($employee, $year);

        if ($this->usedFor($employee, $year) >= $limit) {
            throw ValidationException::withMessages([
                'holiday_id' => "Optional holiday limit reached for {$year} ({$limit} allowed).",
            ]);
        }

        // Re-claiming after a cancellation reuses the same row (unique per employee + holiday).
        $claim = $existing ?? new OptionalHolidayClaim(['employee_id' => $employee->id, 'holiday_id' => $holiday->id]);
        $claim->fill([
            'status' => OptionalHolidayClaim::STATUS_CLAIMED,
            'reason' => $reason,
            'claimed_by' => $by?->id,
            'cancelled_at' => null,
        ])->save();

        return $claim;
    }

    public function cancel(OptionalHolidayClaim $claim): OptionalHolidayClaim
    {
        if ($claim->status !== OptionalHolidayClaim::STATUS_CLAIMED) {
            throw ValidationException::withMessages(['status' => 'Only a claimed optional holiday can be cancelled.']);
        }

        if (! $claim->holiday->date->gt(Carbon::today())) {
            throw ValidationException::withMessages(['status' => 'An optional holiday can only be cancelled before the day.']);
        }

        $claim->update(['status' => OptionalHolidayClaim::STATUS_CANCELLED, 'cancelled_at' => now()]);

        return $claim;
    }
}
