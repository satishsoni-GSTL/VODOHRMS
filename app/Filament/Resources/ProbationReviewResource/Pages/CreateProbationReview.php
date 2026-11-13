<?php

namespace App\Filament\Resources\ProbationReviewResource\Pages;

use App\Filament\Concerns\PrefillsFromReapply;
use App\Filament\Resources\ProbationReviewResource;
use App\Models\Employee;
use App\Services\ProbationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProbationReview extends CreateRecord
{
    use PrefillsFromReapply {
        mount as prefillMount;
    }

    protected static string $resource = ProbationReviewResource::class;

    protected static ?string $title = 'New Probation Review';

    public function mount(): void
    {
        $this->prefillMount();

        // Opened from the "Probation Due" list with ?employee={id}.
        $employeeId = (int) request()->query('employee');

        if ($employeeId && ! request()->query('reapply')) {
            $this->form->fill([...$this->form->getRawState(), 'employee_id' => $employeeId]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        // The employee picker only lists reviewable employees, but re-check server-side so a
        // tampered request can't review someone outside the user's reporting line.
        $allowed = ProbationReviewResource::reviewableEmployeeIds();
        abort_unless($allowed === null || in_array((int) $data['employee_id'], $allowed, true), 403);

        return app(ProbationService::class)->submit(
            Employee::findOrFail($data['employee_id']),
            $data,
            auth()->user(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Probation review submitted for approval';
    }

    protected function reapplyFields(): array
    {
        return ['employee_id', 'recommendation', 'extension_days', 'effective_date', 'rating', 'performance_summary', 'improvement_areas'];
    }
}
