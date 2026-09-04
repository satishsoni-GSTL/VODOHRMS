<?php

namespace App\Filament\Pages;

use App\Models\PayrollRun;
use App\Models\PayrollRunDeductionException;
use App\Services\PrePayrollDeductionService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PrePayrollDeductionReview extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string $view = 'filament.pages.pre-payroll-deduction-review';

    protected static ?string $title = 'Review Other Deductions';

    public ?int $run = null;

    public ?PayrollRun $payrollRun = null;

    protected ?Collection $rowCache = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('payroll.process'), 403);

        $this->run ??= request()->integer('run') ?: null;

        abort_unless($this->run !== null, 404);

        $this->payrollRun = PayrollRun::with('company')->findOrFail($this->run);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return $this->rowCache ??= app(PrePayrollDeductionService::class)->rows($this->payrollRun);
    }

    /**
     * Rows grouped by employee for the view.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function rowsByEmployee(): Collection
    {
        return $this->rows()
            ->groupBy(fn (array $row) => $row['employee_code'].' — '.$row['employee_name'])
            ->sortKeys();
    }

    public function summary(): array
    {
        $rows = $this->rows();

        return [
            'total' => $rows->count(),
            'waived' => $rows->filter(fn (array $r) => $r['exception'] !== null)->count(),
            'active_amount' => $rows->filter(fn (array $r) => $r['exception'] === null)->sum('amount'),
            'waived_amount' => $rows->filter(fn (array $r) => $r['exception'] !== null)->sum('amount'),
        ];
    }

    public function waiveAction(): Action
    {
        return Action::make('waive')
            ->label('Grant Exception')
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->modalHeading('Waive this deduction for this run')
            ->modalDescription(fn (array $arguments) => $this->rowFor($arguments['key'] ?? null)['label'] ?? '')
            ->form([
                Textarea::make('reason')->required()->label('Reason for the exception'),
            ])
            ->action(function (array $arguments, array $data): void {
                $row = $this->rowFor($arguments['key'] ?? null);

                if (! $row) {
                    return;
                }

                app(PrePayrollDeductionService::class)->grantException(
                    $this->payrollRun, $row, auth()->user(), $data['reason']
                );

                $this->rowCache = null;

                Notification::make()->title('Deduction waived for this run')->success()->send();
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Remove Exception')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $exception = PayrollRunDeductionException::query()
                    ->where('payroll_run_id', $this->payrollRun->id)
                    ->find($arguments['id'] ?? null);

                if ($exception) {
                    app(PrePayrollDeductionService::class)->removeException($exception);
                    $this->rowCache = null;
                    Notification::make()->title('Exception removed')->success()->send();
                }
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function rowFor(?string $key): ?array
    {
        return $key === null ? null : $this->rows()->firstWhere('key', $key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToRun')
                ->label('Back to Payroll Run')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\Resources\PayrollRunResource::getUrl('view', ['record' => $this->run])),
        ];
    }
}
