<?php

namespace App\Filament\Resources\ExpenseClaimResource\Pages;

use App\Filament\Concerns\LocksEmployeeToSelf;
use App\Filament\Resources\ExpenseClaimResource;
use App\Models\ExpenseClaim;
use App\Services\ExpenseClaimService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Correct-and-resubmit screen for a sent-back claim. Access is gated by
 * ExpenseClaimResource::canEdit (sent-back only); saving routes the claim back
 * through the approval workflow via ExpenseClaimService::resubmit.
 */
class EditExpenseClaim extends EditRecord
{
    use LocksEmployeeToSelf;

    protected static string $resource = ExpenseClaimResource::class;

    public function getTitle(): string
    {
        return "Edit & Resubmit {$this->record->claim_number}";
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines()
            ->orderBy('id')
            ->get(['category_id', 'expense_date', 'requested_amount', 'vendor', 'bill_number', 'payment_mode', 'receipt_path', 'description'])
            ->map(fn ($line) => [
                ...$line->toArray(),
                'expense_date' => $line->expense_date?->toDateString(),
            ])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ExpenseClaim $record */
        return app(ExpenseClaimService::class)->resubmit(
            $record,
            $data['claim_date'],
            $data['project_client'] ?? null,
            array_values($data['lines'] ?? []),
        );
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Claim resubmitted for approval';
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()->label('Save & Resubmit');
    }

    protected function getRedirectUrl(): ?string
    {
        return ExpenseClaimResource::getUrl('view', ['record' => $this->record]);
    }

    protected function employeeOverridePermission(): string
    {
        return 'expense.manage';
    }
}
