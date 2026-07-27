<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use App\Services\PayrollCalculationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PayrollCalculationService::class)->getOrCreateRun($data['payroll_month'], $data['company_id']);
    }
}
