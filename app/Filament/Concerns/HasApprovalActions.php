<?php

namespace App\Filament\Concerns;

use App\Models\ApprovalAction;
use App\Services\ApprovalWorkflowService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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

        $modalContent = fn (Model $record) => static::approvalModalContent($record) ?? new HtmlString('');

        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($canAct)
                ->requiresConfirmation()
                ->modalContent($modalContent)
                ->action(function (Model $record) {
                    static::actOnRecord($record, ApprovalAction::ACTION_APPROVE);
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($canAct)
                ->modalContent($modalContent)
                ->form([Textarea::make('remarks')->required()->label('Reason for rejection')])
                ->action(function (Model $record, array $data) {
                    static::actOnRecord($record, ApprovalAction::ACTION_REJECT, $data['remarks']);
                }),
            Action::make('send_back')
                ->label('Send Back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible($canAct)
                ->modalContent($modalContent)
                ->form([Textarea::make('remarks')->required()->label('What needs to change')])
                ->action(function (Model $record, array $data) {
                    static::actOnRecord($record, ApprovalAction::ACTION_SEND_BACK, $data['remarks']);
                }),
        ];
    }

    /**
     * Optional extra content rendered above the confirmation/remarks form in the approve/
     * reject/send-back modals, so approvers aren't acting blind. Override in a resource to
     * surface entity-specific detail (e.g. expense line items and receipts); default is none.
     */
    protected static function approvalModalContent(Model $record): ?View
    {
        return null;
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
