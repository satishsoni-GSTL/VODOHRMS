<?php

namespace App\Exports\Reports;

use App\Filament\Resources\AppraisalResource;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * PMS export for one cycle: "Summary" (one row per employee: reviewer, stage, scores,
 * rating band, calibration) and "Goals" (every goal with weight, self and manager rating
 * and comments). Scoped like the Appraisals list — HR everyone, managers their line.
 *
 * Manager ratings are left out for the viewer's own appraisal until the manager has submitted.
 */
class AppraisalReportExport implements WithMultipleSheets
{
    public function __construct(private readonly AppraisalCycle $cycle, private readonly User $user) {}

    public function sheets(): array
    {
        $appraisals = AppraisalResource::scoped(
            Appraisal::query()->where('appraisal_cycle_id', $this->cycle->id),
            $this->user,
        )
            ->with(['employee.department', 'employee.designation', 'manager', 'goals'])
            ->get()
            ->sortBy(fn (Appraisal $a) => $a->employee?->employee_code)
            ->values();

        $hideManager = fn (Appraisal $a) => $a->employee_id === $this->user->employee_id && ! $a->managerReviewVisibleToEmployee();

        return [
            new class($appraisals, $hideManager) implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
            {
                public function __construct(private $appraisals, private $hideManager) {}

                public function title(): string
                {
                    return 'Summary';
                }

                public function headings(): array
                {
                    return ['Employee Code', 'Name', 'Department', 'Designation', 'Reviewer', 'Stage', 'Goals', 'Total Weight %',
                        'Self Score', 'Manager Score', 'Final Rating', 'Rating Band', 'HR Calibration Note',
                        'Goals Submitted', 'Goals Approved', 'Self Review Submitted', 'Manager Review Submitted'];
                }

                public function collection()
                {
                    return $this->appraisals->map(function (Appraisal $a) {
                        $hide = ($this->hideManager)($a);
                        $band = $hide || $a->final_rating === null ? null : trim(explode('·', Appraisal::ratingLabel($a->final_rating))[1] ?? '');

                        return [
                            $a->employee?->employee_code,
                            trim(preg_replace('/\s+/', ' ', (string) $a->employee?->full_name)),
                            $a->employee?->department?->name,
                            $a->employee?->designation?->name,
                            $a->manager ? trim(preg_replace('/\s+/', ' ', $a->manager->full_name)) : 'HR',
                            Appraisal::STATUSES[$a->status] ?? $a->status,
                            $a->goals->count(),
                            $a->goals->sum('weightage'),
                            $a->self_score,
                            $hide ? null : $a->manager_score,
                            $hide ? null : $a->final_rating,
                            $band,
                            $hide ? null : $a->final_remarks,
                            $a->goals_submitted_at?->format('Y-m-d'),
                            $a->goals_approved_at?->format('Y-m-d'),
                            $a->self_submitted_at?->format('Y-m-d'),
                            $a->manager_submitted_at?->format('Y-m-d'),
                        ];
                    });
                }
            },
            new class($appraisals, $hideManager) implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
            {
                public function __construct(private $appraisals, private $hideManager) {}

                public function title(): string
                {
                    return 'Goals';
                }

                public function headings(): array
                {
                    return ['Employee Code', 'Name', 'Goal', 'Target / Measure', 'Weight %', 'Self Rating', 'Self Comments', 'Manager Rating', 'Manager Comments'];
                }

                public function collection()
                {
                    return $this->appraisals->flatMap(function (Appraisal $a) {
                        $hide = ($this->hideManager)($a);

                        return $a->goals->map(fn ($g) => [
                            $a->employee?->employee_code,
                            trim(preg_replace('/\s+/', ' ', (string) $a->employee?->full_name)),
                            $g->title,
                            $g->target,
                            $g->weightage,
                            $g->self_rating,
                            $g->self_comments,
                            $hide ? null : $g->manager_rating,
                            $hide ? null : $g->manager_comments,
                        ]);
                    })->values();
                }
            },
            new class($appraisals, $hideManager) implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
            {
                private const BANDS = ['Outstanding', 'Exceeds expectations', 'Meets expectations', 'Partially meets', 'Does not meet'];

                public function __construct(private $appraisals, private $hideManager) {}

                public function title(): string
                {
                    return 'Distribution';
                }

                public function headings(): array
                {
                    return ['Department', ...self::BANDS, 'Not yet rated', 'Total', 'Average Final Rating'];
                }

                /** Employees per rating band, by department, plus an "All" row. */
                public function collection()
                {
                    $band = fn (Appraisal $a) => ($this->hideManager)($a) || $a->final_rating === null
                        ? null
                        : trim(explode('·', Appraisal::ratingLabel($a->final_rating))[1] ?? '');

                    $row = function (string $label, $group) use ($band) {
                        $bands = $group->map($band);
                        $rated = $group->filter(fn (Appraisal $a) => $band($a) !== null);

                        return [
                            $label,
                            ...array_map(fn ($b) => $bands->filter(fn ($x) => $x === $b)->count(), self::BANDS),
                            $bands->filter(fn ($x) => $x === null)->count(),
                            $group->count(),
                            $rated->isNotEmpty() ? round($rated->avg('final_rating'), 2) : null,
                        ];
                    };

                    return $this->appraisals
                        ->groupBy(fn (Appraisal $a) => $a->employee?->department?->name ?? '—')
                        ->sortKeys()
                        ->map(fn ($group, $dept) => $row((string) $dept, $group))
                        ->values()
                        ->push($row('All', $this->appraisals));
                }
            },
        ];
    }
}
