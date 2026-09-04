<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunDeductionException extends Model
{
    public const SOURCE_PAYROLL_INPUT = 'payroll_input';

    public const SOURCE_STRUCTURE_LINE = 'salary_structure_line';

    public const SOURCE_STATUTORY = 'statutory';

    public const SOURCES = [
        self::SOURCE_PAYROLL_INPUT => 'Payroll Input',
        self::SOURCE_STRUCTURE_LINE => 'Salary Structure',
        self::SOURCE_STATUTORY => 'Statutory',
    ];

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'source_type', 'source_id',
        'component_code', 'label', 'amount', 'reason', 'waived_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /**
     * Stable key identifying the deduction this exception waives, matched against the
     * same key built in PayrollCalculationService / PrePayrollDeductionService.
     *
     * The employee id is part of the key on purpose: a waiver applies to that one
     * employee only, never to everyone sharing the same statutory component code.
     */
    public static function keyFor(int $employeeId, string $sourceType, ?int $sourceId, ?string $componentCode): string
    {
        return $employeeId.'|'.$sourceType.'|'.($sourceId ?? '').'|'.strtoupper($componentCode ?? '');
    }

    public function key(): string
    {
        return self::keyFor($this->employee_id, $this->source_type, $this->source_id, $this->component_code);
    }
}
