<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingChecklist extends Model
{
    public const STEPS = [
        'personal_details_done', 'documents_done', 'statutory_done', 'bank_done',
        'department_done', 'salary_done', 'login_done', 'asset_allocation_done',
    ];

    protected $fillable = [
        'employee_id', 'personal_details_done', 'documents_done', 'statutory_done', 'bank_done',
        'department_done', 'salary_done', 'login_done', 'asset_allocation_done', 'completion_percent',
    ];

    protected function casts(): array
    {
        return array_fill_keys(self::STEPS, 'boolean');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recalculateCompletion(): void
    {
        $done = collect(self::STEPS)->filter(fn ($step) => $this->{$step})->count();
        $this->completion_percent = (int) round(($done / count(self::STEPS)) * 100);
    }
}
