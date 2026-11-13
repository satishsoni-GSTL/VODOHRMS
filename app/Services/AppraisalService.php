<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\AppraisalGoal;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\PerformanceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Performance appraisal flow, per employee per cycle:
 *
 *   goals_draft ──(employee submits; weightages total 100)──► goals_submitted
 *   goals_submitted ──(manager approves)──► goals_approved   (or returns ──► goals_draft)
 *   [HR opens the review phase]
 *   goals_approved ──(employee self-rates)──► self_submitted
 *   self_submitted ──(manager rates)──► manager_submitted    (weighted score = final rating)
 *   [HR may calibrate the final rating, then closes the cycle] ──► closed
 *
 * Every write goes through here and only touches what the acting user may change at the
 * appraisal's current stage — forms can't widen that.
 */
class AppraisalService
{
    use NotifiesRecipients;

    /** HR tier: may act as reviewer on any appraisal and run cycles. */
    public function isHr(User $user): bool
    {
        return $user->can('employee.edit');
    }

    public function isEmployee(Appraisal $appraisal, User $user): bool
    {
        return $user->employee_id !== null && $appraisal->employee_id === $user->employee_id;
    }

    public function isManager(Appraisal $appraisal, User $user): bool
    {
        return ($user->employee_id !== null && $appraisal->manager_id === $user->employee_id) || $this->isHr($user);
    }

    private function cycleOpen(Appraisal $appraisal): bool
    {
        return in_array($appraisal->cycle->status, [AppraisalCycle::STATUS_GOAL_SETTING, AppraisalCycle::STATUS_REVIEW], true);
    }

    public function canEditGoals(Appraisal $appraisal, User $user): bool
    {
        if (! $this->cycleOpen($appraisal)) {
            return false;
        }

        return match ($appraisal->status) {
            Appraisal::STATUS_GOALS_DRAFT => $this->isEmployee($appraisal, $user) || $this->isHr($user),
            // The manager may fine-tune goals before approving them.
            Appraisal::STATUS_GOALS_SUBMITTED => $this->isManager($appraisal, $user) && ! $this->isEmployee($appraisal, $user),
            default => false,
        };
    }

    public function canApproveGoals(Appraisal $appraisal, User $user): bool
    {
        return $this->cycleOpen($appraisal)
            && $appraisal->status === Appraisal::STATUS_GOALS_SUBMITTED
            && $this->isManager($appraisal, $user)
            && ! $this->isEmployee($appraisal, $user);
    }

    public function canSelfReview(Appraisal $appraisal, User $user): bool
    {
        return $appraisal->cycle->status === AppraisalCycle::STATUS_REVIEW
            && $appraisal->status === Appraisal::STATUS_GOALS_APPROVED
            && $this->isEmployee($appraisal, $user);
    }

    public function canManagerReview(Appraisal $appraisal, User $user): bool
    {
        return $appraisal->cycle->status === AppraisalCycle::STATUS_REVIEW
            && $appraisal->status === Appraisal::STATUS_SELF_SUBMITTED
            && $this->isManager($appraisal, $user)
            && ! $this->isEmployee($appraisal, $user);
    }

    public function canCalibrate(Appraisal $appraisal, User $user): bool
    {
        return $appraisal->status === Appraisal::STATUS_MANAGER_SUBMITTED
            && $appraisal->cycle->status !== AppraisalCycle::STATUS_CLOSED
            && $this->isHr($user)
            && ! $this->isEmployee($appraisal, $user);
    }

    // ---- Cycle --------------------------------------------------------------------------

    /**
     * Create an appraisal for every active / probation / notice-period employee in scope who
     * joined before the period ended, and open goal setting.
     */
    public function launch(AppraisalCycle $cycle): int
    {
        if ($cycle->status !== AppraisalCycle::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'This cycle has already been launched.']);
        }

        $employees = Employee::query()
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->when($cycle->company_id, fn ($q) => $q->where('company_id', $cycle->company_id))
            ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhere('date_of_joining', '<=', $cycle->period_end->toDateString()))
            ->with('user')
            ->get();

        DB::transaction(function () use ($cycle, $employees) {
            foreach ($employees as $employee) {
                Appraisal::firstOrCreate(
                    ['appraisal_cycle_id' => $cycle->id, 'employee_id' => $employee->id],
                    ['manager_id' => $employee->reporting_manager_id, 'status' => Appraisal::STATUS_GOALS_DRAFT],
                );
            }

            $cycle->update(['status' => AppraisalCycle::STATUS_GOAL_SETTING]);
        });

        $due = $cycle->goal_setting_due ? ' by '.$cycle->goal_setting_due->format('d M Y') : '';
        $notice = new PerformanceNotification(
            "Set your goals: {$cycle->name}",
            "The {$cycle->name} appraisal cycle has started. Please add your goals (KRAs) and submit them to your manager{$due}.",
        );

        foreach ($employees as $employee) {
            $this->notifyEmployee($employee, $notice);
        }

        return $employees->count();
    }

    public function startReview(AppraisalCycle $cycle): void
    {
        if ($cycle->status !== AppraisalCycle::STATUS_GOAL_SETTING) {
            throw ValidationException::withMessages(['status' => 'The review phase can only start after goal setting.']);
        }

        $cycle->update(['status' => AppraisalCycle::STATUS_REVIEW]);

        $due = $cycle->review_due ? ' by '.$cycle->review_due->format('d M Y') : '';
        $notice = new PerformanceNotification(
            "Self review open: {$cycle->name}",
            "Please rate yourself against your approved goals and submit your self review{$due}.",
        );

        $cycle->appraisals()->where('status', Appraisal::STATUS_GOALS_APPROVED)->with('employee.user')->get()
            ->each(fn (Appraisal $a) => $this->notifyEmployee($a->employee, $notice));
    }

    /** Close the cycle: reviewed appraisals are finalised; the rest stay as they are (incomplete). */
    public function closeCycle(AppraisalCycle $cycle): int
    {
        if ($cycle->status !== AppraisalCycle::STATUS_REVIEW) {
            throw ValidationException::withMessages(['status' => 'Only a cycle in the review phase can be closed.']);
        }

        return DB::transaction(function () use ($cycle) {
            $closed = $cycle->appraisals()->where('status', Appraisal::STATUS_MANAGER_SUBMITTED)
                ->update(['status' => Appraisal::STATUS_CLOSED]);

            $cycle->update(['status' => AppraisalCycle::STATUS_CLOSED]);

            return $closed;
        });
    }

    // ---- Appraisal ----------------------------------------------------------------------

    /**
     * Save whatever this user may edit right now. Fields they may not change are ignored.
     *
     * @param  array{goals?: array<int, array<string, mixed>>, self_overall_comments?: string|null, manager_overall_comments?: string|null}  $data
     */
    public function save(Appraisal $appraisal, array $data, User $user): Appraisal
    {
        $goals = collect($data['goals'] ?? [])->values();

        DB::transaction(function () use ($appraisal, $goals, $data, $user) {
            if ($this->canEditGoals($appraisal, $user)) {
                $keep = [];

                foreach ($goals as $i => $row) {
                    if (blank($row['title'] ?? null)) {
                        continue;
                    }

                    $attributes = [
                        'title' => $row['title'],
                        'description' => $row['description'] ?? null,
                        'target' => $row['target'] ?? null,
                        'weightage' => max(0, min(100, (int) ($row['weightage'] ?? 0))),
                        'sequence' => $i,
                    ];

                    $goal = ! empty($row['id']) ? $appraisal->goals()->whereKey($row['id'])->first() : null;

                    if ($goal) {
                        $goal->update($attributes);
                    } else {
                        $goal = $appraisal->goals()->create($attributes);
                    }

                    $keep[] = $goal->id;
                }

                $appraisal->goals()->whereNotIn('id', $keep)->delete();
            }

            if ($this->canSelfReview($appraisal, $user)) {
                $this->saveRatings($appraisal, $goals, 'self');
                $appraisal->update(['self_overall_comments' => $data['self_overall_comments'] ?? $appraisal->self_overall_comments]);
            }

            if ($this->canManagerReview($appraisal, $user)) {
                $this->saveRatings($appraisal, $goals, 'manager');
                $appraisal->update(['manager_overall_comments' => $data['manager_overall_comments'] ?? $appraisal->manager_overall_comments]);
            }
        });

        return $appraisal->fresh('goals');
    }

    private function saveRatings(Appraisal $appraisal, $goals, string $who): void
    {
        foreach ($goals as $row) {
            $goal = ! empty($row['id']) ? $appraisal->goals()->whereKey($row['id'])->first() : null;

            if (! $goal) {
                continue;
            }

            $rating = $row["{$who}_rating"] ?? null;

            $goal->update([
                "{$who}_rating" => $rating === null || $rating === '' ? null : max(1, min(5, (int) $rating)),
                "{$who}_comments" => $row["{$who}_comments"] ?? null,
            ]);
        }
    }

    public function submitGoals(Appraisal $appraisal, User $user): void
    {
        if (! $this->canEditGoals($appraisal, $user) || $appraisal->status !== Appraisal::STATUS_GOALS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'You cannot submit goals for this appraisal now.']);
        }

        $goals = $appraisal->goals()->get();
        $min = $appraisal->cycle->min_goals;

        if ($goals->count() < $min) {
            throw ValidationException::withMessages(['goals' => "Add at least {$min} goals."]);
        }

        if ((int) $goals->sum('weightage') !== 100) {
            throw ValidationException::withMessages(['goals' => 'Goal weightages must add up to 100% (now '.$goals->sum('weightage').'%).']);
        }

        if ($goals->contains(fn (AppraisalGoal $g) => $g->weightage < 1)) {
            throw ValidationException::withMessages(['goals' => 'Every goal needs a weightage of at least 1%.']);
        }

        $appraisal->update(['status' => Appraisal::STATUS_GOALS_SUBMITTED, 'goals_submitted_at' => now(), 'goals_return_remarks' => null]);

        $this->notifyManager($appraisal, new PerformanceNotification(
            'Goals to approve: '.$this->name($appraisal->employee),
            $this->name($appraisal->employee)." has submitted goals for {$appraisal->cycle->name}. Please review and approve them.",
            "/admin/appraisals/{$appraisal->id}/edit",
        ));
    }

    public function approveGoals(Appraisal $appraisal, User $user): void
    {
        if (! $this->canApproveGoals($appraisal, $user)) {
            throw ValidationException::withMessages(['status' => 'You cannot approve these goals.']);
        }

        if ((int) $appraisal->goals()->sum('weightage') !== 100) {
            throw ValidationException::withMessages(['goals' => 'Goal weightages must add up to 100% before approval.']);
        }

        $appraisal->update(['status' => Appraisal::STATUS_GOALS_APPROVED, 'goals_approved_at' => now()]);

        $this->notifyEmployee($appraisal->employee, new PerformanceNotification(
            'Your goals are approved',
            "Your goals for {$appraisal->cycle->name} have been approved by your manager.",
            "/admin/appraisals/{$appraisal->id}/edit",
        ));
    }

    public function returnGoals(Appraisal $appraisal, User $user, string $remarks): void
    {
        if (! $this->canApproveGoals($appraisal, $user)) {
            throw ValidationException::withMessages(['status' => 'You cannot return these goals.']);
        }

        if (blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Say what needs to change.']);
        }

        $appraisal->update(['status' => Appraisal::STATUS_GOALS_DRAFT, 'goals_return_remarks' => $remarks]);

        $this->notifyEmployee($appraisal->employee, new PerformanceNotification(
            'Your goals need changes',
            "Your manager returned your goals for {$appraisal->cycle->name}: {$remarks}",
            "/admin/appraisals/{$appraisal->id}/edit",
        ));
    }

    public function submitSelfReview(Appraisal $appraisal, User $user): void
    {
        if (! $this->canSelfReview($appraisal, $user)) {
            throw ValidationException::withMessages(['status' => 'You cannot submit a self review for this appraisal now.']);
        }

        $goals = $appraisal->goals()->get();

        if ($goals->contains(fn (AppraisalGoal $g) => $g->self_rating === null)) {
            throw ValidationException::withMessages(['goals' => 'Rate yourself on every goal before submitting.']);
        }

        $appraisal->update([
            'status' => Appraisal::STATUS_SELF_SUBMITTED,
            'self_submitted_at' => now(),
            'self_score' => $this->weightedScore($goals, 'self_rating'),
        ]);

        $this->notifyManager($appraisal, new PerformanceNotification(
            'Review due: '.$this->name($appraisal->employee),
            $this->name($appraisal->employee)." has completed their self review for {$appraisal->cycle->name}. Please add your ratings.",
            "/admin/appraisals/{$appraisal->id}/edit",
        ));
    }

    public function submitManagerReview(Appraisal $appraisal, User $user): void
    {
        if (! $this->canManagerReview($appraisal, $user)) {
            throw ValidationException::withMessages(['status' => 'You cannot submit the manager review for this appraisal now.']);
        }

        $goals = $appraisal->goals()->get();

        if ($goals->contains(fn (AppraisalGoal $g) => $g->manager_rating === null)) {
            throw ValidationException::withMessages(['goals' => 'Rate every goal before submitting.']);
        }

        $score = $this->weightedScore($goals, 'manager_rating');

        $appraisal->update([
            'status' => Appraisal::STATUS_MANAGER_SUBMITTED,
            'manager_submitted_at' => now(),
            'manager_score' => $score,
            'final_rating' => $score,
        ]);

        $this->notifyEmployee($appraisal->employee, new PerformanceNotification(
            'Your appraisal review is complete',
            "Your manager has completed your review for {$appraisal->cycle->name}. Overall rating: ".Appraisal::ratingLabel($score).'.',
            "/admin/appraisals/{$appraisal->id}/edit",
        ));
    }

    /** HR calibration (normalisation) of the final rating, with a reason. */
    public function calibrate(Appraisal $appraisal, User $user, float $finalRating, string $remarks): void
    {
        if (! $this->canCalibrate($appraisal, $user)) {
            throw ValidationException::withMessages(['status' => 'This appraisal cannot be calibrated now.']);
        }

        if ($finalRating < 1 || $finalRating > 5) {
            throw ValidationException::withMessages(['final_rating' => 'The rating must be between 1 and 5.']);
        }

        if (blank($remarks)) {
            throw ValidationException::withMessages(['final_remarks' => 'Give a reason for the change.']);
        }

        $appraisal->update(['final_rating' => round($finalRating, 2), 'final_remarks' => $remarks]);
    }

    /** HR changes who reviews an appraisal (e.g. the manager left). */
    public function reassignManager(Appraisal $appraisal, ?int $managerEmployeeId): void
    {
        $appraisal->update(['manager_id' => $managerEmployeeId]);
    }

    /** Σ(rating × weightage) / Σ weightage, on the 1-5 scale. */
    public function weightedScore($goals, string $field): ?float
    {
        $weight = $goals->sum('weightage');

        return $weight > 0
            ? round($goals->sum(fn (AppraisalGoal $g) => (int) $g->{$field} * $g->weightage) / $weight, 2)
            : null;
    }

    private function notifyManager(Appraisal $appraisal, PerformanceNotification $notice): void
    {
        if ($appraisal->manager) {
            $this->notifyEmployee($appraisal->manager, $notice);
        }
    }

    private function name(?Employee $employee): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $employee?->full_name));
    }
}
