<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Services\PayrollCalculationService;
use App\Services\PayslipService;
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
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('paid_days'),
                Tables\Columns\TextColumn::make('lop_days'),
                Tables\Columns\TextColumn::make('gross_earnings')->money('INR'),
                Tables\Columns\TextColumn::make('total_deductions')->money('INR'),
                Tables\Columns\TextColumn::make('net_pay')->money('INR')->weight('bold'),
                Tables\Columns\IconColumn::make('payslip.id')->label('Payslip')->boolean()
                    ->getStateUsing(fn (PayrollRunEmployee $record) => $record->payslip()->exists()),
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
            Action::make('calculate')
                ->label('Calculate / Recalculate')
                ->icon('heroicon-o-play')
                ->visible(fn () => $this->record->isEditable() && auth()->user()->can('payroll.process'))
                ->requiresConfirmation()
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
        ])->columns(3);
    }
}
