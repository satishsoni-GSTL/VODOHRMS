<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ProbationReview;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\ProbationDecisionNotification;
use App\Support\DocumentLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProbationService
{
    use NotifiesRecipients;

    public function __construct(private readonly ApprovalWorkflowService $workflow) {}

    /**
     * Employees on probation whose probation ends within $withinDays from today (overdue
     * ones included), soonest first. Filtered in PHP because the end date depends on each
     * employee's own probation period.
     *
     * @param  array<int>|null  $onlyEmployeeIds  restrict to these employees (a manager's team)
     * @return Collection<int, Employee>
     */
    public function dueEmployees(int $withinDays = 30, ?array $onlyEmployeeIds = null): Collection
    {
        $cutoff = Carbon::today()->addDays($withinDays);

        return Employee::query()
            ->with(['reportingManager', 'designation', 'department'])
            ->where('status', Employee::STATUS_PROBATION)
            ->whereNotNull('date_of_joining')
            ->when($onlyEmployeeIds !== null, fn ($q) => $q->whereIn('id', $onlyEmployeeIds))
            ->get()
            ->filter(fn (Employee $e) => $e->probationEndDate()->lessThanOrEqualTo($cutoff))
            ->sortBy(fn (Employee $e) => $e->probationEndDate()->timestamp)
            ->values();
    }

    /** A review is already awaiting approval (a sent-back one can be resubmitted). */
    public function hasOpenReview(Employee $employee): bool
    {
        return $employee->probationReviews()->where('status', ProbationReview::STATUS_PENDING)->exists();
    }

    /**
     * @param  array{recommendation: string, extension_days?: int|null, effective_date?: string|null, rating?: int|null, performance_summary: string, improvement_areas?: string|null}  $data
     */
    public function submit(Employee $employee, array $data, User $submitter): ProbationReview
    {
        if ($employee->status !== Employee::STATUS_PROBATION) {
            throw ValidationException::withMessages(['employee_id' => 'This employee is not on probation.']);
        }

        if (! $employee->date_of_joining) {
            throw ValidationException::withMessages(['employee_id' => 'This employee has no date of joining, so the probation end date is unknown.']);
        }

        if ($this->hasOpenReview($employee)) {
            throw ValidationException::withMessages(['employee_id' => 'A probation review for this employee is already awaiting approval.']);
        }

        $endDate = $employee->probationEndDate();
        $isExtend = $data['recommendation'] === ProbationReview::RECOMMEND_EXTEND;
        $extensionDays = $isExtend ? (int) ($data['extension_days'] ?? 0) : null;

        if ($isExtend && ($extensionDays < 1 || $extensionDays > 365)) {
            throw ValidationException::withMessages(['extension_days' => 'Enter an extension of 1 to 365 days.']);
        }

        $effectiveDate = $isExtend
            ? $endDate->copy()->addDays($extensionDays)
            : (! empty($data['effective_date']) ? Carbon::parse($data['effective_date']) : $endDate->copy());

        return DB::transaction(function () use ($employee, $data, $submitter, $endDate, $extensionDays, $effectiveDate) {
            // A sent-back review is superseded by this resubmission.
            $employee->probationReviews()->where('status', ProbationReview::STATUS_SENT_BACK)
                ->update(['status' => ProbationReview::STATUS_REJECTED, 'decided_at' => now()]);

            $review = ProbationReview::create([
                'employee_id' => $employee->id,
                'probation_end_date' => $endDate->toDateString(),
                'recommendation' => $data['recommendation'],
                'extension_days' => $extensionDays,
                'effective_date' => $effectiveDate->toDateString(),
                'rating' => $data['rating'] ?? null,
                'performance_summary' => $data['performance_summary'],
                'improvement_areas' => $data['improvement_areas'] ?? null,
                'submitted_by' => $submitter->id,
                'status' => ProbationReview::STATUS_PENDING,
            ]);

            $this->workflow->submit($review);

            return $review->fresh();
        });
    }

    /** Called once the review is finally approved: update the employee, issue the letter. */
    public function applyApproved(ProbationReview $review): void
    {
        $employee = $review->employee;

        if ($review->recommendation === ProbationReview::RECOMMEND_CONFIRM) {
            $employee->update([
                'status' => Employee::STATUS_ACTIVE,
                'confirmation_date' => $review->effective_date->toDateString(),
            ]);
        } else {
            $employee->update([
                'probation_period_days' => ($employee->probation_period_days ?? Employee::DEFAULT_PROBATION_DAYS) + $review->extension_days,
            ]);
        }

        $review->update(['letter_path' => $this->generateLetter($review->fresh())]);

        $this->notifyEmployee($employee->fresh(), new ProbationDecisionNotification($review->fresh()));
    }

    public function generateLetter(ProbationReview $review): string
    {
        $employee = $review->employee()->with(['company', 'designation', 'department'])->first();

        $pdf = Pdf::loadView('letters.probation', [
            'review' => $review,
            'employee' => $employee,
            'company' => $employee->company,
            'logo' => DocumentLogo::dataUri($employee),
            'issuedOn' => ($review->decided_at ?? now())->format('d M Y'),
        ]);

        $path = "probation-letters/{$employee->id}/review-{$review->id}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
