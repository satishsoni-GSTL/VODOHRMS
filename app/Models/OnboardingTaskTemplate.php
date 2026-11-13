<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class OnboardingTaskTemplate extends Model
{
    public const ASSIGNEE_HR = 'hr';

    public const ASSIGNEE_MANAGER = 'reporting_manager';

    public const ASSIGNEE_EMPLOYEE = 'employee';

    public const ASSIGNEE_ROLE = 'role';

    public const ASSIGNEE_USER = 'user';

    public const ASSIGNEE_TYPES = [
        self::ASSIGNEE_HR => "HR (employee's HR manager, else HR Admins)",
        self::ASSIGNEE_MANAGER => 'Reporting manager',
        self::ASSIGNEE_EMPLOYEE => 'The new joiner',
        self::ASSIGNEE_ROLE => 'Everyone with a role',
        self::ASSIGNEE_USER => 'A specific person (e.g. IT / Admin owner)',
    ];

    protected $fillable = [
        'title', 'description', 'assignee_type', 'role_id', 'user_id', 'due_offset_days', 'company_id', 'sequence', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'due_offset_days' => 'integer'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDueLabelAttribute(): string
    {
        $d = $this->due_offset_days;

        return match (true) {
            $d === 0 => 'On joining day',
            $d < 0 => abs($d).' day'.(abs($d) === 1 ? '' : 's').' before joining',
            default => "{$d} day".($d === 1 ? '' : 's').' after joining',
        };
    }
}
