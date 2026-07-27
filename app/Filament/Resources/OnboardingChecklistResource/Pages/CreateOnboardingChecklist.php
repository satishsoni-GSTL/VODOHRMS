<?php

namespace App\Filament\Resources\OnboardingChecklistResource\Pages;

use App\Filament\Resources\OnboardingChecklistResource;
use App\Models\Employee;
use App\Services\OnboardingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOnboardingChecklist extends CreateRecord
{
    protected static string $resource = OnboardingChecklistResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(OnboardingService::class)->refresh(Employee::findOrFail($data['employee_id']));
    }
}
