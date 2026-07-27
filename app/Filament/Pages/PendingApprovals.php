<?php

namespace App\Filament\Pages;

use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Services\ApprovalWorkflowService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PendingApprovals extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Pending Approvals';

    protected static string $view = 'filament.pages.pending-approvals';

    protected static ?int $navigationSort = 0;

    public function table(Table $table): Table
    {
        $workflow = app(ApprovalWorkflowService::class);
        $user = auth()->user();

        $actionableIds = ApprovalInstance::query()
            ->where('status', ApprovalInstance::STATUS_PENDING)
            ->with('requestable')
            ->get()
            ->filter(fn (ApprovalInstance $instance) => $instance->requestable && $workflow->canUserActOnInstance($instance, $user))
            ->pluck('id');

        return $table
            ->query(fn () => ApprovalInstance::query()->whereIn('id', $actionableIds))
            ->columns([
                Tables\Columns\TextColumn::make('workflowDefinition.name')->label('Type'),
                Tables\Columns\TextColumn::make('requestable_type')
                    ->label('Request')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),
                Tables\Columns\TextColumn::make('requestable.employee.full_name')->label('Employee'),
                Tables\Columns\TextColumn::make('current_level')->label('Level'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Submitted'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(fn (ApprovalInstance $record) => $this->act($record, ApprovalAction::ACTION_APPROVE)),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->form([Textarea::make('remarks')->required()])
                    ->action(fn (ApprovalInstance $record, array $data) => $this->act($record, ApprovalAction::ACTION_REJECT, $data['remarks'])),
                Tables\Actions\Action::make('send_back')
                    ->label('Send Back')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->form([Textarea::make('remarks')->required()])
                    ->action(fn (ApprovalInstance $record, array $data) => $this->act($record, ApprovalAction::ACTION_SEND_BACK, $data['remarks'])),
            ]);
    }

    private function act(ApprovalInstance $instance, string $action, ?string $remarks = null): void
    {
        try {
            app(ApprovalWorkflowService::class)->act($instance, auth()->user(), $action, $remarks);
            Notification::make()->title('Approval action recorded')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
