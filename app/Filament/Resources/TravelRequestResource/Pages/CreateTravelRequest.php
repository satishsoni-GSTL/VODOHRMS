<?php

namespace App\Filament\Resources\TravelRequestResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Concerns\PrefillsFromReapply;
use App\Filament\Resources\TravelRequestResource;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Services\TravelService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTravelRequest extends CreateRecord
{
    use LocksEmployeeToSelf;
    use PrefillsFromReapply {
        mount as prefillMount;
    }

    protected static string $resource = TravelRequestResource::class;

    /** The sent-back request being reapplied (captured on first load; later Livewire requests lack the query). */
    public ?int $reapplyId = null;

    public function mount(): void
    {
        $this->prefillMount();
        $this->reapplyId = request()->query('reapply') ? (int) request()->query('reapply') : null;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Reapplying replaces the sent-back request rather than leaving it open.
        $replacing = $this->reapplyId
            ? TravelRequestResource::getEloquentQuery()->whereKey($this->reapplyId)->where('status', TravelRequest::STATUS_SENT_BACK)->first()
            : null;

        return app(TravelService::class)->request(Employee::findOrFail($data['employee_id']), $data, $replacing);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Travel request submitted for approval';
    }

    protected function reapplyFields(): array
    {
        return ['employee_id', 'travel_type', 'purpose', 'from_location', 'to_location', 'departure_date', 'return_date',
            'travel_mode', 'accommodation_required', 'estimated_cost', 'advance_requested'];
    }

    protected function employeeOverridePermission(): string
    {
        return 'expense.manage';
    }
}
