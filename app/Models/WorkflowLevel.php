<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowLevel extends Model
{
    public const APPROVER_REPORTING_MANAGER = 'reporting_manager';

    public const APPROVER_DEPARTMENT_HEAD = 'department_head';

    public const APPROVER_HR = 'hr';

    public const APPROVER_FINANCE = 'finance';

    public const APPROVER_MANAGEMENT = 'management';

    public const APPROVER_SPECIFIC_ROLE = 'specific_role';

    public const APPROVER_SPECIFIC_USER = 'specific_user';

    protected $fillable = [
        'workflow_definition_id', 'sequence', 'approver_type',
        'approver_role_id', 'approver_user_id', 'condition_rules',
    ];

    protected function casts(): array
    {
        return ['condition_rules' => 'array'];
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
