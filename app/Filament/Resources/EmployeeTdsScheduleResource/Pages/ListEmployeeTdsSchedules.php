<?php

namespace App\Filament\Resources\EmployeeTdsScheduleResource\Pages;

use App\Filament\Resources\EmployeeTdsScheduleResource;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Services\IncomeTaxCalculationService;
use App\Services\TdsScheduleService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListEmployeeTdsSchedules extends ListRecords
{
    protected static string $resource = EmployeeTdsScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate TDS')
                ->icon('heroicon-o-calculator')
                ->visible(fn () => auth()->user()->can('tax.manage'))
                ->modalDescription('Calculates each employee\'s annual tax and spreads what is still due equally from the start month to the end of the year. Months before the start month keep the amount saved there (TDS deducted before HRMS) and count as already deducted. Months with finalized payroll always show the TDS actually deducted.')
                ->form([
                    Forms\Components\Select::make('financial_year_id')
                        ->label('Financial Year')
                        ->options(FinancialYear::query()->orderByDesc('start_date')->pluck('name', 'id'))
                        ->default(fn () => app(IncomeTaxCalculationService::class)->financialYearForMonth(now()->format('Y-m'))?->id)
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('start_month')
                        ->label('Deduct from month')
                        ->helperText('Usually the month HRMS payroll goes live.')
                        ->options(function (Get $get) {
                            $year = FinancialYear::find($get('financial_year_id'));

                            return $year ? collect(app(IncomeTaxCalculationService::class)->financialYearMonths($year))
                                ->mapWithKeys(fn ($m) => [$m => Carbon::createFromFormat('Y-m', $m)->format('M Y')])
                                ->all() : [];
                        })
                        ->default(now()->format('Y-m'))
                        ->required(),
                    Forms\Components\Select::make('employee_ids')
                        ->label('Employees')
                        ->helperText('Leave empty to generate for all active employees.')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => $this->activeEmployees()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}"])),
                    Forms\Components\Toggle::make('overwrite_manual')
                        ->label('Overwrite manually edited months')
                        ->helperText('Off: amounts HR has edited are kept, and the remaining tax is spread over the other months.')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $year = FinancialYear::findOrFail($data['financial_year_id']);
                    $employees = empty($data['employee_ids'])
                        ? $this->activeEmployees()
                        : Employee::whereIn('id', $data['employee_ids'])->get();

                    $service = app(TdsScheduleService::class);

                    try {
                        foreach ($employees as $employee) {
                            $service->generate($employee, $year, $data['start_month'], (bool) $data['overwrite_manual'], auth()->user());
                        }
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title("TDS schedule generated for {$employees->count()} employee(s)")
                        ->body('Review and edit any month below — payroll deducts the last saved amount.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function activeEmployees()
    {
        return Employee::query()
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->orderBy('employee_code')
            ->get();
    }
}
