<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many optional holidays may be claimed in a calendar year. A row without an
 * employee is the year's default; a row with one overrides it for that employee.
 */
class OptionalHolidayLimit extends Model
{
    use Auditable;

    protected function auditModule(): string
    {
        return 'attendance';
    }

    protected $fillable = ['year', 'employee_id', 'max_claims'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'max_claims' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
