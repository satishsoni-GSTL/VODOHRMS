<?php

namespace App\Models\Concerns;

use App\Services\AuditLogService;

/**
 * Writes a create/update/delete row to audit_logs for models that carry sensitive data.
 * Override auditedEvents()/auditModule() on the using model to narrow which events are
 * logged (e.g. workflow-request models only log 'created' here — their status transitions
 * are logged centrally by ApprovalWorkflowService::act() instead, to avoid double-logging).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (in_array('created', $model->auditedEvents(), true)) {
                app(AuditLogService::class)->log('create', $model, [], $model->auditableAttributes($model->getAttributes()), module: $model->auditModule());
            }
        });

        static::updated(function ($model) {
            if (! in_array('updated', $model->auditedEvents(), true)) {
                return;
            }

            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $model->getOriginal($key)])->all();
            app(AuditLogService::class)->log('update', $model, $old, $changes, module: $model->auditModule());
        });

        static::deleted(function ($model) {
            if (in_array('deleted', $model->auditedEvents(), true)) {
                app(AuditLogService::class)->log('delete', $model, $model->auditableAttributes($model->getAttributes()), [], module: $model->auditModule());
            }
        });
    }

    protected function auditedEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    protected function auditModule(): string
    {
        return class_basename($this);
    }

    private function auditableAttributes(array $attributes): array
    {
        return collect($attributes)->except(['created_at', 'updated_at', 'password'])->all();
    }
}
