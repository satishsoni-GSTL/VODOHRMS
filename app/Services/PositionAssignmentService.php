<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Position;
use App\Models\PositionAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves people in and out of field-force positions (joining, transfer, promotion, exit)
 * and keeps the assignment history. Optionally re-points employees.reporting_manager_id
 * so the existing approval workflows (leave, expense, WFH...) follow the field hierarchy:
 * a person reports to the nearest *filled* position above theirs, so an MR under a vacant
 * ABM seat reports to the RBM until the ABM seat is filled.
 */
class PositionAssignmentService
{
    public function assign(
        Position $position,
        Employee $employee,
        CarbonInterface|string|null $startDate = null,
        string $reason = 'joining',
        ?string $remarks = null,
        bool $syncReportingManagers = true,
        ?int $userId = null,
    ): Position {
        $start = Carbon::parse($startDate ?? now())->startOfDay();

        if ($position->employee_id === $employee->id) {
            throw ValidationException::withMessages(['employee_id' => 'This employee already holds this position.']);
        }

        return DB::transaction(function () use ($position, $employee, $start, $reason, $remarks, $syncReportingManagers, $userId) {
            // Whoever sits in the seat now leaves it.
            if ($position->employee_id) {
                $this->closeAssignment($position, $position->employee_id, $start->copy()->subDay(), $reason);
            }

            // The incoming person leaves any seat they hold today (that's what a transfer is).
            $previousSeats = Position::query()->where('employee_id', $employee->id)->whereKeyNot($position->id)->get();

            foreach ($previousSeats as $seat) {
                $this->closeAssignment($seat, $employee->id, $start->copy()->subDay(), $reason);
                $seat->forceFill(['employee_id' => null, 'vacant_since' => $start->toDateString()])->save();
            }

            $position->forceFill(['employee_id' => $employee->id, 'vacant_since' => null])->save();

            PositionAssignment::create([
                'position_id' => $position->id,
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'reason' => $reason,
                'remarks' => $remarks,
                'created_by' => $userId,
            ]);

            $this->syncEmployeeFromPosition($employee, $position);

            if ($syncReportingManagers) {
                $this->syncReportingLines($position);
                foreach ($previousSeats as $seat) {
                    $this->syncReportingLines($seat);
                }
            }

            return $position->refresh();
        });
    }

    public function vacate(
        Position $position,
        CarbonInterface|string|null $lastDate = null,
        string $reason = 'exit',
        bool $syncReportingManagers = true,
    ): Position {
        if (! $position->employee_id) {
            return $position;
        }

        $last = Carbon::parse($lastDate ?? now())->startOfDay();

        return DB::transaction(function () use ($position, $last, $reason, $syncReportingManagers) {
            $this->closeAssignment($position, $position->employee_id, $last, $reason);

            $position->forceFill(['employee_id' => null, 'vacant_since' => $last->copy()->addDay()->toDateString()])->save();

            if ($syncReportingManagers) {
                $this->syncReportingLines($position);
            }

            return $position->refresh();
        });
    }

    /**
     * Re-derives reporting_manager_id around one position: its incumbent reports to the
     * nearest filled seat above, and the incumbents of the seats below (walking through
     * vacant seats) report to this seat's incumbent - or further up if this seat is vacant.
     */
    public function syncReportingLines(Position $position): int
    {
        $changed = 0;
        $position->refresh();

        if ($position->employee_id) {
            $managerId = $position->nearestFilledAncestor()?->employee_id;
            $changed += $this->setManager($position->employee_id, $managerId);
        }

        $managerForChildren = $position->employee_id ?: $position->nearestFilledAncestor()?->employee_id;

        foreach ($this->filledSeatsDirectlyBelow($position) as $child) {
            $changed += $this->setManager($child->employee_id, $managerForChildren);
        }

        return $changed;
    }

    /** Re-derives every field reporting line from the position tree. Returns rows changed. */
    public function syncAll(): int
    {
        $changed = 0;

        Position::query()->whereNotNull('employee_id')->with('parent')->each(function (Position $position) use (&$changed) {
            $changed += $this->setManager($position->employee_id, $position->nearestFilledAncestor()?->employee_id);
        });

        return $changed;
    }

    /** First filled seats under $position, looking through vacant ones. */
    private function filledSeatsDirectlyBelow(Position $position): array
    {
        $result = [];
        $frontier = [$position->id];
        $seen = [$position->id];

        while ($frontier !== []) {
            $children = Position::query()->whereIn('parent_position_id', $frontier)->whereNotIn('id', $seen)->get();
            $frontier = [];

            foreach ($children as $child) {
                $seen[] = $child->id;

                if ($child->employee_id) {
                    $result[] = $child;
                } else {
                    $frontier[] = $child->id;
                }
            }
        }

        return $result;
    }

    private function setManager(int $employeeId, ?int $managerId): int
    {
        if ($managerId === $employeeId) {
            return 0;
        }

        $employee = Employee::query()->find($employeeId);

        if (! $employee || $employee->reporting_manager_id === $managerId) {
            return 0;
        }

        if ($managerId && app(EmployeeHierarchyService::class)->wouldCreateCircularReference($employeeId, $managerId)) {
            return 0;
        }

        $employee->update(['reporting_manager_id' => $managerId]);

        return 1;
    }

    private function closeAssignment(Position $position, int $employeeId, CarbonInterface $endDate, string $reason): void
    {
        $open = PositionAssignment::query()
            ->where('position_id', $position->id)
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->get();

        foreach ($open as $assignment) {
            $end = $endDate->lt($assignment->start_date) ? $assignment->start_date : $endDate;
            $assignment->update([
                'end_date' => $end->toDateString(),
                'remarks' => trim(($assignment->remarks ? $assignment->remarks.' | ' : '').'Closed: '.(PositionAssignment::REASONS[$reason] ?? $reason)),
            ]);
        }
    }

    /** Copies the seat's division / HQ onto the employee record so reports and filters work. */
    private function syncEmployeeFromPosition(Employee $employee, Position $position): void
    {
        $position->loadMissing('hierarchyLevel', 'geography');

        $updates = [];

        if ($position->division_id) {
            $updates['division_id'] = $position->division_id;
        }

        if ($position->geography_id) {
            $updates['headquarter_id'] = $position->geography_id;
        }

        $workforce = $position->hierarchyLevel?->workforce_type;
        if (in_array($workforce, ['field', 'ho'], true)) {
            $updates['workforce_type'] = $workforce;
        }

        if ($updates !== []) {
            $employee->update($updates);
        }
    }
}
