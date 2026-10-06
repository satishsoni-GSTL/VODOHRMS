<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HR waived an employee's Loss of Pay for one payroll run: the run pays them as if every
 * LOP day were a paid day. Kept separate from payroll_run_employees so it survives
 * recalculation; the underlying attendance / LWP leave records are left untouched.
 */
class PayrollRunLopWaiver extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'waived_days', 'reason', 'waived_by'];

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
}
