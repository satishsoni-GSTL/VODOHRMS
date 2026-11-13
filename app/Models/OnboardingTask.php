<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class OnboardingTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_DONE => 'Done',
        self::STATUS_SKIPPED => 'Skipped',
    ];

    protected $fillable = [
        'employee_id', 'template_id', 'title', 'description', 'assignee_type', 'assigned_user_id', 'role_id',
        'due_date', 'status', 'completed_by', 'completed_at', 'remarks', 'sequence',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTaskTemplate::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->due_date?->lt(today());
    }

    public function getOwnerLabelAttribute(): string
    {
        return $this->assignedUser?->name ?? ($this->role?->name ? "{$this->role->name} (any)" : '—');
    }

    /**
     * Tasks this user is responsible for: assigned to them, to a role they hold, or — for a
     * joiner's own tasks, created before they had a login — to them as the employee.
     */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        $roleIds = $user->roles()->pluck('id')->all();

        return $query->where(fn (Builder $q) => $q
            ->where('assigned_user_id', $user->id)
            ->when($roleIds !== [], fn (Builder $q) => $q->orWhereIn('role_id', $roleIds))
            ->when($user->employee_id, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q
                ->where('assignee_type', OnboardingTaskTemplate::ASSIGNEE_EMPLOYEE)
                ->where('employee_id', $user->employee_id))));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_user_id === $user->id
            || ($this->role_id && $user->roles()->whereKey($this->role_id)->exists())
            || ($this->assignee_type === OnboardingTaskTemplate::ASSIGNEE_EMPLOYEE && $this->employee_id === $user->employee_id);
    }
}
