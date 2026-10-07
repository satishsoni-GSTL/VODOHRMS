<?php

namespace App\Filament\Resources\OptionalHolidayClaimResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Resources\OptionalHolidayClaimResource;
use App\Models\Employee;
use App\Models\Holiday;
use App\Services\OptionalHolidayService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateOptionalHolidayClaim extends CreateRecord
{
    use LocksEmployeeToSelf;

    protected static string $resource = OptionalHolidayClaimResource::class;

    protected static ?string $title = 'Claim Optional Holiday';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(OptionalHolidayService::class)->claim(
                Employee::findOrFail($data['employee_id']),
                Holiday::findOrFail($data['holiday_id']),
                $data['reason'] ?? null,
                auth()->user(),
            );
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function employeeOverridePermission(): string
    {
        return 'attendance.manage';
    }
}
