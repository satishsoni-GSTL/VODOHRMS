<?php

namespace App\Filament\Concerns;

use App\Models\ApprovalAction;
use App\Services\ApprovalWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Shared approve/reject/send-back actions for any Filament resource whose model has an
 * `approvalInstance()` relation and implements App\Contracts\Approvable.
 *
 * - approvalActions()        row actions for the resource table (Tables\Actions\Action)
 * - approvalHeaderActions()  the same three as page header actions (Actions\Action), for a
 *                            ViewRecord page so approvers can act without going back to the list
 */
trait HasApprovalActions
{
    protected static function approvalActions(): array
    {
        return [
            ...static::buildApprovalActions(\Filament\Tables\Actions\Action::class),
            static::buildSubmitForApprovalAction(\Filament\Tables\Actions\Action::class),
        ];
    }

    public static function approvalHeaderActions(): array
    {
        return [
            ...static::buildApprovalActions(Action::class),
            static::buildSubmitForApprovalAction(Action::class),
        ];
    }

    /**
     * @param  class-string<\Filament\Tables\Actions\Action|Action>  $actionClass
     */
    private static function buildApprovalActions(string $actionClass): array
    {
        $canAct = fn (Model $record) => $record->approval_instance_id
            && app(ApprovalWorkflowService::class)->canUserActOnInstance($record->approvalInstance, auth()->user());

        $modalContent = fn (Model $record) => static::approvalModalContent($record) ?? new HtmlString('');

        return [
            $actionClass::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($canAct)
                ->requiresConfirmation()
                ->modalContent($modalContent)
                ->action(function (Model $record) {
                    static::actOnRecord($record, ApprovalAction::ACTION_APPROVE);
                }),
            $actionClass::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($canAct)
                ->modalContent($modalContent)
                ->form([Textarea::make('remarks')->required()->label('Reason for rejection')])
                ->action(function (Model $record, array $data) {
                    static::actOnRecord($record, ApprovalAction::ACTION_REJECT, $data['remarks']);
                }),
            $actionClass::make('send_back')
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
     * Rescue action for a request that never got routed into the workflow (its
     * approval_instance_id is null — e.g. it was raised before the workflow was configured,
     * or the submit failed). Without an instance nobody can approve it. Visible only to
     * HR/admins for the module, and only while the request is unrouted.
     *
     * @param  class-string<\Filament\Tables\Actions\Action|Action>  $actionClass
     */
    private static function buildSubmitForApprovalAction(string $actionClass)
    {
        return $actionClass::make('submitForApproval')
            ->label('Submit for Approval')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('This request has no approval routing yet. Send it into the approval workflow now.')
            ->visible(fn (Model $record) => $record->approval_instance_id === null
                && app(ApprovalWorkflowService::class)->userCanManageModule($record->getApprovalModule(), auth()->user()))
            ->action(function (Model $record) {
                try {
                    app(ApprovalWorkflowService::class)->submit($record);
                    Notification::make()->title('Sent for approval')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Could not submit for approval')->body($e->getMessage())->danger()->send();
                }
            });
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
