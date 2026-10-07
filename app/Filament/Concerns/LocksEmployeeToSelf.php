<?php

namespace App\Filament\Concerns;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

/**
 * Server-side lock for the "Employee" field on self-service request forms.
 *
 * Those forms show the field disabled for ordinary employees, but a disabled field is only
 * locked in the browser: its value can still be changed from the browser console before
 * submitting (e.g. to file an attendance regularization in someone else's name). For users
 * without the form's HR permission this rebuilds employee_id on the server — their own
 * employee on create, the record's existing employee on edit — and audit-logs any attempt
 * to submit a different one.
 *
 * Use on a CreateRecord / EditRecord page and implement employeeOverridePermission().
 */
trait LocksEmployeeToSelf
{
    /** The permission that lets a user pick another employee on this form (e.g. 'leave.manage'). */
    abstract protected function employeeOverridePermission(): string;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->lockEmployee($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->lockEmployee($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function lockEmployee(array $data): array
    {
        $user = auth()->user();

        if ($user->can($this->employeeOverridePermission())) {
            return $data;
        }

        $allowed = isset($this->record) && $this->record?->exists
            ? $this->record->employee_id
            : $user->employee_id;

        abort_if($allowed === null, 403, 'Your login is not linked to an employee record.');

        $submitted = $data['employee_id'] ?? null;

        if ($submitted !== null && (int) $submitted !== (int) $allowed) {
            $reason = 'Blocked: employee changed from the browser on '.class_basename(static::class)
                ." (submitted employee #{$submitted}, allowed #{$allowed}).";

            Log::warning('Form tampering blocked', ['user' => $user->id, 'page' => static::class, 'submitted' => $submitted, 'allowed' => $allowed]);
            app(AuditLogService::class)->log(
                'tampering_blocked',
                $this->record ?? null,
                ['employee_id' => (int) $submitted],
                ['employee_id' => (int) $allowed],
                reason: $reason,
                module: 'security',
            );
        }

        $data['employee_id'] = $allowed;

        return $data;
    }
}
