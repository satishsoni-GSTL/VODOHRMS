<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    public const MODULE_LEAVE = 'leave';

    public const MODULE_ATTENDANCE_REGULARIZATION = 'attendance_regularization';

    public const MODULE_EXPENSE = 'expense';

    public const MODULE_LOAN = 'loan';

    public const MODULE_RESIGNATION = 'resignation';

    protected $fillable = ['module', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(WorkflowLevel::class)->orderBy('sequence');
    }
}
