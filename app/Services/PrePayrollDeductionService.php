<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollInput;
use App\Models\PayrollRun;
use App\Models\PayrollRunDeductionException;
use App\Models\SalaryComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the "other deductions" picture for a payroll run so HR can review — and
 * selectively waive — individual deductions before the run is calculated.
 *
 * A waiver (PayrollRunDeductionException) only affects the run it is attached to;
 * the underlying payroll input / salary-structure line / statutory rule is untouched
 * and applies again next month.
 */
class PrePayrollDeductionService
{
    /** Tokens inside a salary-component code that mark it as a statutory deduction. */
    private const STATUTORY_TOKENS = ['PF', 'EPF', 'VPF', 'ESI', 'ESIC', 'PT', 'LWF', 'MLWF'];

    public const TDS_CODE = 'TDS';

    public function __construct(private readonly IncomeTaxCalculationService $incomeTax) {}

    /**
     * A statutory deduction is keyed by its component code (so the waiver survives a salary
     * revision within the month), everything else by the structure line id.
     */
    public static function isStatutoryCode(?string $code): bool
    {
        $code = strtoupper((string) $code);

        if ($code === '') {
            return false;
        }

        $tokens = preg_split('/[^A-Z0-9]+/', $code) ?: [];

        if (array_intersect($tokens, self::STATUTORY_TOKENS) !== []) {
            return true;
        }

        return str_contains($code, 'PROFESSIONAL')
            || (str_contains($code, 'PROF') && str_contains($code, 'TAX'));
    }

    /**
     * Every non-loan deduction that would hit this run, one array row each:
     * key, employee_id, employee_name, employee_code, source_type, source_id,
     * component_code, label, amount, exception (PayrollRunDeductionException|null).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(PayrollRun $run): Collection
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $run->payroll_month)->endOfMonth();

        $employees = Employee::query()
            ->where('company_id', $run->company_id)
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->orderBy('employee_code')
            ->get();

        $exceptions = $run->deductionExceptions()->get()->keyBy(fn (PayrollRunDeductionException $e) => $e->key());

        $inputsByEmployee = PayrollInput::query()
            ->where('payroll_month', $run->payroll_month)
            ->whereIn('type', PayrollInput::DEDUCTION_TYPES)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $financialYear = $this->incomeTax->financialYearForMonth($run->payroll_month);

        $rows = collect();

        foreach ($employees as $employee) {
            $structure = $employee->salaryStructures()
                ->where('effective_from', '<=', $monthEnd->toDateString())
                ->orderByDesc('effective_from')
                ->first();

            if ($structure) {
                foreach ($structure->lines()->with('component')->get() as $line) {
                    $component = $line->component;

                    if (! $component || $component->type !== SalaryComponent::TYPE_DEDUCTION) {
                        continue;
                    }

                    $isStatutory = self::isStatutoryCode($component->code);

                    $rows->push($this->makeRow(
                        $employee,
                        $isStatutory ? PayrollRunDeductionException::SOURCE_STATUTORY : PayrollRunDeductionException::SOURCE_STRUCTURE_LINE,
                        $isStatutory ? null : $line->id,
                        $isStatutory ? strtoupper((string) $component->code) : null,
                        $component->name,
                        (float) $line->monthly_amount,
                        $exceptions,
                    ));
                }
            }

            foreach ($inputsByEmployee->get($employee->id, collect()) as $input) {
                $rows->push($this->makeRow(
                    $employee,
                    PayrollRunDeductionException::SOURCE_PAYROLL_INPUT,
                    $input->id,
                    null,
                    PayrollInput::TYPES[$input->type] ?? $input->type,
                    (float) $input->amount,
                    $exceptions,
                ));
            }

            if ($financialYear) {
                $tds = $this->incomeTax->monthlyTdsForPayroll($employee, $financialYear, $run->payroll_month);

                if ($tds > 0) {
                    $rows->push($this->makeRow(
                        $employee,
                        PayrollRunDeductionException::SOURCE_STATUTORY,
                        null,
                        self::TDS_CODE,
                        IncomeTaxCalculationService::TDS_LABEL,
                        $tds,
                        $exceptions,
                    ));
                }
            }
        }

        return $rows->values();
    }

    /**
     * Set of waiver keys for a run — the calculation service checks membership to skip
     * a deduction. See PayrollRunDeductionException::keyFor().
     *
     * @return array<string, true>
     */
    public function waivedKeySet(PayrollRun $run): array
    {
        return $run->deductionExceptions()
            ->get()
            ->mapWithKeys(fn (PayrollRunDeductionException $e) => [$e->key() => true])
            ->all();
    }

    public function grantException(PayrollRun $run, array $row, User $user, string $reason): PayrollRunDeductionException
    {
        return $run->deductionExceptions()->updateOrCreate(
            [
                'employee_id' => $row['employee_id'],
                'source_type' => $row['source_type'],
                'source_id' => $row['source_id'],
                'component_code' => $row['component_code'],
            ],
            [
                'label' => $row['label'],
                'amount' => $row['amount'],
                'reason' => $reason,
                'waived_by' => $user->id,
            ]
        );
    }

    public function removeException(PayrollRunDeductionException $exception): void
    {
        $exception->delete();
    }

    /**
     * @param  Collection<string, PayrollRunDeductionException>  $exceptions
     * @return array<string, mixed>
     */
    private function makeRow(
        Employee $employee,
        string $sourceType,
        ?int $sourceId,
        ?string $componentCode,
        string $label,
        float $amount,
        Collection $exceptions,
    ): array {
        $key = PayrollRunDeductionException::keyFor($employee->id, $sourceType, $sourceId, $componentCode);

        return [
            'key' => $key,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'component_code' => $componentCode,
            'label' => $label,
            'amount' => round($amount, 2),
            'exception' => $exceptions->get($key),
        ];
    }
}
