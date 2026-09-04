<?php

namespace App\Filament\Pages;

use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Services\ApprovalWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PendingApprovals extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Pending Approvals';

    protected static string $view = 'filament.pages.pending-approvals';

    protected static ?int $navigationSort = 0;

    /** Selected workflow-type filter (workflow_definition_id), or null for all. */
    public ?string $typeFilter = null;

    protected ?Collection $cache = null;

    public static function getNavigationBadge(): ?string
    {
        try {
            $workflow = app(ApprovalWorkflowService::class);
            $user = auth()->user();

            $count = ApprovalInstance::query()
                ->where('status', ApprovalInstance::STATUS_PENDING)
                ->with('requestable.employee')
                ->get()
                ->filter(fn (ApprovalInstance $i) => $i->requestable && $workflow->canUserActOnInstance($i, $user))
                ->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Every pending request the current user may act on, newest first, with the
     * requestable + its employee eager-loaded.
     *
     * @return Collection<int, ApprovalInstance>
     */
    public function actionableInstances(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $workflow = app(ApprovalWorkflowService::class);
        $user = auth()->user();

        return $this->cache = ApprovalInstance::query()
            ->where('status', ApprovalInstance::STATUS_PENDING)
            ->when($this->typeFilter, fn ($q) => $q->where('workflow_definition_id', $this->typeFilter))
            ->with(['requestable.employee', 'workflowDefinition', 'actions.approver'])
            ->latest()
            ->get()
            ->filter(fn (ApprovalInstance $i) => $i->requestable && $workflow->canUserActOnInstance($i, $user))
            ->values();
    }

    /**
     * @return Collection<string, Collection<int, ApprovalInstance>>
     */
    public function groupedByEmployee(): Collection
    {
        return $this->actionableInstances()
            ->groupBy(fn (ApprovalInstance $i) => $i->requestable->employee?->full_name ?? 'Unknown')
            ->sortKeys();
    }

    /**
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        return \App\Models\WorkflowDefinition::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** Plain-language summary of what a request is asking for. */
    public function summarize(ApprovalInstance $instance): string
    {
        $r = $instance->requestable;

        return match (class_basename($r)) {
            'LeaveApplication' => sprintf(
                '%s · %s → %s (%s day%s)%s',
                $r->leaveType?->name ?? 'Leave',
                $r->from_date?->format('d M Y'),
                $r->to_date?->format('d M Y'),
                rtrim(rtrim((string) $r->days, '0'), '.'),
                $r->days == 1 ? '' : 's',
                $r->is_half_day ? ' · half day' : '',
            ),
            'AttendanceRegularization' => sprintf(
                'Regularize %s · %s',
                $r->attendance_date?->format('d M Y'),
                \App\Models\AttendanceRegularization::TYPES[$r->request_type] ?? $r->request_type,
            ),
            'WorkFromHomeRequest' => sprintf(
                'WFH · %s → %s',
                $r->from_date?->format('d M Y'),
                $r->to_date?->format('d M Y'),
            ),
            'ExpenseClaim' => sprintf(
                'Expense %s · ₹%s · %d item(s)',
                $r->claim_number,
                number_format((float) $r->total_requested_amount, 2),
                $r->lines()->count(),
            ),
            'EmployeeLoan' => sprintf(
                '%s · ₹%s · %s installment(s)',
                $r->type === \App\Models\EmployeeLoan::TYPE_SALARY_ADVANCE ? 'Salary Advance' : 'Loan',
                number_format((float) $r->requested_amount, 2),
                $r->installments ?? '—',
            ),
            default => class_basename($r),
        };
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve request')
            ->modalDescription(fn (array $arguments) => $this->describe($arguments))
            ->modalContent(fn (array $arguments) => $this->modalDetail($arguments))
            ->action(fn (array $arguments) => $this->act($arguments['id'], ApprovalAction::ACTION_APPROVE));
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Reject request')
            ->modalDescription(fn (array $arguments) => $this->describe($arguments))
            ->modalContent(fn (array $arguments) => $this->modalDetail($arguments))
            ->form([Textarea::make('remarks')->required()->label('Reason for rejection')])
            ->action(fn (array $arguments, array $data) => $this->act($arguments['id'], ApprovalAction::ACTION_REJECT, $data['remarks']));
    }

    public function sendBackAction(): Action
    {
        return Action::make('sendBack')
            ->label('Send Back')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->modalHeading('Send request back')
            ->modalDescription(fn (array $arguments) => $this->describe($arguments))
            ->modalContent(fn (array $arguments) => $this->modalDetail($arguments))
            ->form([Textarea::make('remarks')->required()->label('What needs to change')])
            ->action(fn (array $arguments, array $data) => $this->act($arguments['id'], ApprovalAction::ACTION_SEND_BACK, $data['remarks']));
    }

    /** Approve every listed request for one employee in a single click. */
    public function approveAllAction(): Action
    {
        return Action::make('approveAll')
            ->label('Approve all')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->size('sm')
            ->link()
            ->requiresConfirmation()
            ->modalHeading('Approve all listed requests for this employee')
            ->action(function (array $arguments) {
                $ok = 0;
                $failed = 0;

                foreach ((array) $arguments['ids'] as $id) {
                    try {
                        $this->act($id, ApprovalAction::ACTION_APPROVE, notify: false);
                        $ok++;
                    } catch (\Throwable) {
                        $failed++;
                    }
                }

                Notification::make()
                    ->title("Approved {$ok} request(s)".($failed ? ", {$failed} could not be actioned" : ''))
                    ->{$failed ? 'warning' : 'success'}()
                    ->send();
            });
    }

    private function describe(array $arguments): string
    {
        $instance = $this->actionableInstances()->firstWhere('id', $arguments['id'] ?? null);

        return $instance ? $this->summarize($instance) : '';
    }

    private function modalDetail(array $arguments): ?\Illuminate\Contracts\View\View
    {
        $instance = $this->actionableInstances()->firstWhere('id', $arguments['id'] ?? null);

        if (! $instance) {
            return null;
        }

        return view('filament.approvals.summary', ['instance' => $instance]);
    }

    private function act(int $instanceId, string $action, ?string $remarks = null, bool $notify = true): void
    {
        $instance = ApprovalInstance::find($instanceId);

        if (! $instance) {
            return;
        }

        try {
            app(ApprovalWorkflowService::class)->act($instance, auth()->user(), $action, $remarks);
            $this->cache = null;

            if ($notify) {
                Notification::make()->title('Approval action recorded')->success()->send();
            }
        } catch (ValidationException $e) {
            if ($notify) {
                Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                return;
            }

            throw $e;
        }
    }

    public function updatedTypeFilter(): void
    {
        $this->cache = null;
    }
}
