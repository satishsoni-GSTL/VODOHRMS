<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Services\AttendanceMonthlySummaryService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * Monthly attendance register: employees down the Y axis, day-of-month across the X axis,
 * each cell showing the first punch, last punch, and work hours for that day (or a short
 * status code — WO/H/L/A — on days with no punches). Covers all attendance, not just Work
 * From Home; see App\Filament\Pages\WfhReport for the WFH-only equivalent. Complements the
 * existing Monthly Attendance View, which shows only a compact color-coded status per day —
 * this page is the detailed punch/hours register for the same underlying data.
 */
class AttendanceRegister extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Attendance Register';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.attendance-register';

    public string $month = '';

    public string $search = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user->can('attendance.view') || ($user->employee?->directReports()->exists() ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('reports.download', ['type' => 'attendance_register', 'month' => $this->month ?: now()->format('Y-m')]))
                ->openUrlInNewTab(),
        ];
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month ?: now()->format('Y-m'))->startOfMonth();
    }

    public function monthEnd(): Carbon
    {
        return $this->monthStart()->copy()->endOfMonth();
    }

    /**
     * @return int[]
     */
    public function dayNumbers(): array
    {
        return range(1, $this->monthStart()->daysInMonth);
    }

    /**
     * @return LengthAwarePaginator<int, array{employee: Employee, days: array, min_full_day_hours: float}>
     */
    public function rows(): LengthAwarePaginator
    {
        $user = auth()->user();
        $query = Employee::query()->orderBy('employee_code');

        if (! $user->can('attendance.view')) {
            $employee = $user->employee;
            $visibleIds = $employee ? [$employee->id, ...$employee->allSubordinateIds()] : [];
            $query->whereIn('id', $visibleIds);
        }

        if ($this->search !== '') {
            $query->where(fn ($q) => $q
                ->where('employee_code', 'like', "%{$this->search}%")
                ->orWhere('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%"));
        }

        $summaryService = app(AttendanceMonthlySummaryService::class);
        $attendanceService = app(AttendanceService::class);
        $monthStart = $this->monthStart();
        $monthEnd = $this->monthEnd();

        return $query->paginate(15)->through(fn (Employee $employee) => [
            'employee' => $employee,
            'days' => $summaryService->buildForEmployee($employee, $monthStart, $monthEnd),
            // One shift lookup per employee (not per day) — an employee's shift essentially
            // never changes mid-month, so this is an acceptable approximation for coloring.
            'min_full_day_hours' => $attendanceService->minFullDayHoursFor($employee, $monthStart),
        ]);
    }

    public function cellStyle(string $code): string
    {
        $colors = AttendanceMonthlySummaryService::CODE_COLORS[$code] ?? null;

        return $colors ? "background-color:{$colors['bg']};color:{$colors['text']};" : '';
    }

    /**
     * Green when the day's hours meet the full-day threshold, red when they fall short —
     * reuses the exact same palette as the status-code badges (Present/Absent) so the whole
     * page reads with one consistent color language.
     */
    public function hoursStyle(?float $hours, float $minFullDayHours): string
    {
        if ($hours === null) {
            return '';
        }

        $code = $hours >= $minFullDayHours ? AttendanceMonthlySummaryService::CODE_PRESENT : AttendanceMonthlySummaryService::CODE_ABSENT;

        return $this->cellStyle($code);
    }
}
