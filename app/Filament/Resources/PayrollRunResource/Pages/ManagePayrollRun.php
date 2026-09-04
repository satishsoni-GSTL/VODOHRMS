<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Pages\PrePayrollDeductionReview;
use App\Filament\Resources\PayrollRunResource;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Services\PayrollCalculationService;
use App\Services\PayslipService;
use App\Services\PrePayrollDeductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ManagePayrollRun extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PayrollRunResource::class;

    protected static string $view = 'filament.resources.payroll-run-resource.pages.manage-payroll-run';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PayrollRunEmployee::query()->where('payroll_run_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('paid_days'),
                Tables\Columns\TextColumn::make('lop_days')
                    ->label('LOP Days')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('lop_amount')
                    ->label('LOP Amount')
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('gross_earnings')->money('INR'),
                Tables\Columns\TextColumn::make('total_deductions')->money('INR'),
                Tables\Columns\TextColumn::make('net_pay')->money('INR')->weight('bold'),
                Tables\Columns\IconColumn::make('payslip.id')->label('Payslip')->boolean()
                    ->getStateUsing(fn (PayrollRunEmployee $record) => $record->payslip()->exists()),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_lop')
                    ->label('Has LOP')
                    ->query(fn ($query) => $query->where('lop_days', '>', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('generatePayslip')
                    ->label('Generate Payslip')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (PayrollRunEmployee $record) => in_array($this->record->status, [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED], true))
                    ->action(function (PayrollRunEmployee $record) {
                        app(PayslipService::class)->generate($record);
                        Notification::make()->title('Payslip generated')->success()->send();
                    }),
                Tables\Actions\Action::make('downloadPayslip')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (PayrollRunEmployee $record) => $record->payslip()->exists())
                    ->url(fn (PayrollRunEmployee $record) => route('payslips.download', $record->payslip))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportSheet')
                ->label('Export Payroll Sheet')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()->can('payroll.view'))
                ->url(fn () => route('reports.download', ['type' => 'payroll', 'month' => $this->record->payroll_month]))
                ->openUrlInNewTab(),
            Action::make('reviewDeductions')
                ->label('Review Other Deductions')
                ->icon('heroicon-o-receipt-percent')
                ->color('gray')
                ->visible(fn () => $this->record->isEditable() && auth()->user()->can('payroll.process'))
                ->url(fn () => PrePayrollDeductionReview::getUrl(['run' => $this->record->id])),
            Action::make('calculate')
                ->label('Calculate / Recalculate')
                ->icon('heroicon-o-play')
                ->visible(fn () => $this->record->isEditable() && auth()->user()->can('payroll.process'))
                ->requiresConfirmation()
                ->modalDescription(function () {
                    $rows = app(PrePayrollDeductionService::class)->rows($this->record);
                    $others = $rows->filter(fn ($r) => $r['exception'] === null
                        && $r['source_type'] !== \App\Models\PayrollRunDeductionException::SOURCE_STATUTORY);
                    $waived = $rows->filter(fn ($r) => $r['exception'] !== null)->count();

                    $lines = ['This replaces every employee\'s calculation for the run.'];

                    if ($others->isNotEmpty()) {
                        $lines[] = $others->pluck('employee_id')->unique()->count()
                            .' employee(s) have additional (non-statutory) deductions'
                            .($waived > 0 ? ", {$waived} already waived" : '')
                            .'. Use "Review Other Deductions" first if any need an exception.';
                    }

                    return implode(' ', $lines);
                })
                ->action(function () {
                    app(PayrollCalculationService::class)->calculate($this->record);
                    $this->record->refresh();
                    Notification::make()->title('Payroll calculated')->success()->send();
                }),
            Action::make('finalize')
                ->label('Finalize')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, [PayrollRun::STATUS_CALCULATED, PayrollRun::STATUS_REVIEWED], true)
                    && auth()->user()->can('payroll.finalize'))
                ->requiresConfirmation()
                ->action(function () {
                    $this->runAction(fn () => app(PayrollCalculationService::class)->finalize($this->record));
                }),
            Action::make('lock')
                ->label('Lock')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn () => $this->record->status === PayrollRun::STATUS_FINALIZED && auth()->user()->can('payroll.finalize'))
                ->requiresConfirmation()
                ->action(function () {
                    $this->runAction(fn () => app(PayrollCalculationService::class)->lock($this->record, auth()->user()));
                }),
            Action::make('reopen')
                ->label('Reopen')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn () => in_array($this->record->status, [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_LOCKED], true)
                    && auth()->user()->can('payroll.reopen'))
                ->form([Textarea::make('reason')->required()->label('Reason for reopening')])
                ->action(function (array $data) {
                    $this->runAction(fn () => app(PayrollCalculationService::class)->reopen($this->record, $data['reason']));
                }),
        ];
    }

    private function runAction(\Closure $callback): void
    {
        try {
            $callback();
            $this->record->refresh();
            Notification::make()->title('Payroll run updated')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('payroll_month')->label('Payroll Month'),
            TextEntry::make('company.name')->label('Company'),
            TextEntry::make('status')->badge(),
            TextEntry::make('lop_summary')
                ->label('Loss of Pay')
                ->state(function (PayrollRun $record) {
                    $withLop = $record->employees()->where('lop_days', '>', 0);
                    $count = (clone $withLop)->count();

                    if ($count === 0) {
                        return 'None';
                    }

                    $days = (clone $withLop)->sum('lop_days');
                    $amount = (clone $withLop)->sum('lop_amount');

                    return "{$count} employee(s) · {$days} day(s) · ₹".number_format($amount, 2);
                })
                ->badge()
                ->color(fn (string $state) => $state === 'None' ? 'gray' : 'danger'),
        ])->columns(3);
    }
}
