<?php

namespace App\Filament\Resources\EmployeeTaxRegimeResource\Pages;

use App\Filament\Resources\EmployeeTaxRegimeResource;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Services\TaxRegimeSelectionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateEmployeeTaxRegime extends CreateRecord
{
    protected static string $resource = EmployeeTaxRegimeResource::class;

    /**
     * Routed through TaxRegimeSelectionService instead of a plain Eloquent create so the
     * lock (see ARCHITECTURE.md §8) is actually enforced — an employee without `tax.manage`
     * cannot add a new selection once their current one is locked, while HR/Payroll admins
     * always can (that's how they "fix" a regime).
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(TaxRegimeSelectionService::class)->select(
                Employee::findOrFail($data['employee_id']),
                FinancialYear::findOrFail($data['financial_year_id']),
                $data['selected_regime'],
                auth()->user(),
            );
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            throw new Halt();
        }
    }
}
