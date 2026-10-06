<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Services\BulkAttendancePunchService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin-only back-fill of in/out punches for an employee's absent working days.
 * Deliberately not in the navigation — reached by URL: /admin/bulk-attendance-punch
 */
class BulkAttendancePunch extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static string $view = 'filament.pages.bulk-attendance-punch';

    protected static ?string $slug = 'bulk-attendance-punch';

    protected static ?string $title = 'Bulk Attendance Punch (Absent Days)';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('attendance.manage');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'in_time' => '09:30',
            'out_time' => '18:30',
            'dates' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        $refreshDates = fn (Get $get, Set $set) => $set('dates', array_keys($this->absentDayOptions($get)));

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->options(fn () => Employee::query()
                                ->orderBy('employee_code')
                                ->get()
                                ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}"]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated($refreshDates),
                        Forms\Components\DatePicker::make('from_date')
                            ->required()
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated($refreshDates),
                        Forms\Components\DatePicker::make('to_date')
                            ->required()
                            ->maxDate(now())
                            ->afterOrEqual('from_date')
                            ->live()
                            ->afterStateUpdated($refreshDates),
                        Forms\Components\TimePicker::make('in_time')->label('In Time')->seconds(false)->required(),
                        Forms\Components\TimePicker::make('out_time')->label('Out Time')->seconds(false)->required()->after('in_time'),
                        Forms\Components\TextInput::make('remarks')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Biometric device down — attendance confirmed by manager'),
                    ])
                    ->columns(3),
                Forms\Components\CheckboxList::make('dates')
                    ->label('Absent working days')
                    ->helperText('Working days with no attendance or marked Absent. Weekly offs, holidays, leave, WFH, half days, missing punches and payroll-locked days are not listed.')
                    ->options(fn (Get $get) => $this->absentDayOptions($get))
                    ->bulkToggleable()
                    ->columns(4)
                    ->required()
                    ->visible(fn (Get $get) => filled($get('employee_id'))),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function absentDayOptions(Get $get): array
    {
        $employee = Employee::find($get('employee_id'));

        if (! $employee || ! $get('from_date') || ! $get('to_date') || $get('to_date') < $get('from_date')) {
            return [];
        }

        $days = app(BulkAttendancePunchService::class)->absentDays($employee, Carbon::parse($get('from_date')), Carbon::parse($get('to_date')));

        return collect($days)->mapWithKeys(fn (string $d) => [$d => Carbon::parse($d)->format('d M Y (D)')])->all();
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $employee = Employee::findOrFail($data['employee_id']);

        $marked = app(BulkAttendancePunchService::class)->markPresent(
            $employee,
            $data['dates'],
            $data['in_time'],
            $data['out_time'],
            $data['remarks'],
            auth()->user(),
        );

        Notification::make()
            ->title(count($marked).' day(s) marked present for '.$employee->employee_code)
            ->success()
            ->send();

        $this->data['dates'] = [];
    }
}
