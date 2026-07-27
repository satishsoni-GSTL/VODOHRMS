<?php

namespace App\Filament\Concerns;

use App\Models\ApprovalAction;
use App\Services\ApprovalWorkflowService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Shared approve/reject/send-back row actions for any Filament resource whose model has
 * an `approvalInstance()` relation and implements App\Contracts\Approvable.
 */
trait HasApprovalActions
{
    protected static function approvalActions(): array
    {
        $canAct = fn (Model $record) => $record->approval_instance_id
            && app(ApprovalWorkflowService::class)->canUserActOnInstance($record->approvalInstance, auth()->user());

        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($canAct)
                ->requiresConfirmation()
                ->action(function (Model $record) {
                    static::actOnRecord($record, ApprovalAction::ACTION_APPROVE);
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($canAct)
                ->form([Textarea::make('remarks')->required()->label('Reason for rejection')])
                ->action(function (Model $record, array $data) {
                    static::actOnRecord($record, ApprovalAction::ACTION_REJECT, $data['remarks']);
                }),
            Action::make('send_back')
                ->label('Send Back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible($canAct)
                ->form([Textarea::make('remarks')->required()->label('What needs to change')])
                ->action(function (Model $record, array $data) {
                    static::actOnRecord($record, ApprovalAction::ACTION_SEND_BACK, $data['remarks']);
                }),
        ];
    }

    private static function actOnRecord(Model $record, string $action, ?string $remarks = null): void
    {
        try {
            app(ApprovalWorkflowService::class)->act($record->approvalInstance, auth()->user(), $action, $remarks);
            Notification::make()->title('Approval action recorded')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
