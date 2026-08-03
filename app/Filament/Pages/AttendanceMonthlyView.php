<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Services\AttendanceMonthlySummaryService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class AttendanceMonthlyView extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Monthly Attendance View';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.attendance-monthly-view';

    public string $month = '';

    public string $search = '';

    /**
     * Inline hex colors (not Tailwind utility classes) for each status code, since Filament's
     * pre-compiled panel CSS doesn't include arbitrary semantic-color utilities like
     * `bg-success-50` — only what Filament's own components already use. Same palette as
     * AttendanceMonthlySummaryExport::CELL_COLORS, so the portal view and the Excel export match.
     */
    private const CODE_COLORS = [
        AttendanceMonthlySummaryService::CODE_PRESENT => ['bg' => '#C6EFCE', 'text' => '#006100'],
        AttendanceMonthlySummaryService::CODE_HALF_DAY => ['bg' => '#FFEB9C', 'text' => '#9C6500'],
        AttendanceMonthlySummaryService::CODE_WFH => ['bg' => '#DDEBF7', 'text' => '#1F4E78'],
        AttendanceMonthlySummaryService::CODE_ON_DUTY => ['bg' => '#DDEBF7', 'text' => '#1F4E78'],
        AttendanceMonthlySummaryService::CODE_LEAVE => ['bg' => '#E4DFEC', 'text' => '#60497A'],
        AttendanceMonthlySummaryService::CODE_HOLIDAY => ['bg' => '#D9D9D9', 'text' => '#404040'],
        AttendanceMonthlySummaryService::CODE_WEEKLY_OFF => ['bg' => '#F2F2F2', 'text' => '#808080'],
        AttendanceMonthlySummaryService::CODE_ABSENT => ['bg' => '#FFC7CE', 'text' => '#9C0006'],
        AttendanceMonthlySummaryService::CODE_MISSING_PUNCH => ['bg' => '#FCE4D6', 'text' => '#833C00'],
    ];

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user->can('attendance.view') || ($user->employee?->directReports()->exists() ?? false);
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
     * @return LengthAwarePaginator<int, array{employee: Employee, days: array, totals: array<string, int>}>
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
        $monthStart = $this->monthStart();
        $monthEnd = $this->monthEnd();

        return $query->paginate(20)->through(function (Employee $employee) use ($summaryService, $monthStart, $monthEnd) {
            $days = $summaryService->buildForEmployee($employee, $monthStart, $monthEnd);
            $counts = array_count_values(array_column($days, 'code'));

            return [
                'employee' => $employee,
                'days' => $days,
                'totals' => [
                    'P' => $counts[AttendanceMonthlySummaryService::CODE_PRESENT] ?? 0,
                    'HD' => $counts[AttendanceMonthlySummaryService::CODE_HALF_DAY] ?? 0,
                    'L' => $counts[AttendanceMonthlySummaryService::CODE_LEAVE] ?? 0,
                    'H' => $counts[AttendanceMonthlySummaryService::CODE_HOLIDAY] ?? 0,
                    'WO' => $counts[AttendanceMonthlySummaryService::CODE_WEEKLY_OFF] ?? 0,
                    'A' => $counts[AttendanceMonthlySummaryService::CODE_ABSENT] ?? 0,
                ],
            ];
        });
    }

    public function cellStyle(string $code): string
    {
        $colors = self::CODE_COLORS[$code] ?? null;

        return $colors ? "background-color:{$colors['bg']};color:{$colors['text']};" : '';
    }
}
