<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(
        string $action,
        ?Model $auditable,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?string $module = null,
    ): AuditLog {
        return AuditLog::create([
            'action' => $action,
            'module' => $module ?? ($auditable ? class_basename($auditable) : null),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'user_id' => auth()->id(),
            'ip_address' => request()?->ip(),
            'reason' => $reason,
        ]);
    }
}
