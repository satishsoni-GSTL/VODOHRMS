<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRunEmployee extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'salary_structure_id', 'paid_days', 'lop_days', 'lop_amount',
        'gross_earnings', 'total_deductions', 'employer_contributions', 'net_pay', 'status',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryStructure::class, 'salary_structure_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunEmployeeLine::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }
}
