<?php

namespace App\Filament\Resources\ResignationResource\Pages;

use App\Filament\Resources\ResignationResource;
use App\Models\Employee;
use App\Services\ResignationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateResignation extends CreateRecord
{
    protected static string $resource = ResignationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ResignationService::class)->submit(
            Employee::findOrFail($data['employee_id']),
            $data['resignation_date'],
            $data['reason'],
            $data['requested_last_working_date'],
        );
    }
}
