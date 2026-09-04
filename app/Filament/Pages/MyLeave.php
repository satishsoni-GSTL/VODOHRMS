<?php

namespace App\Filament\Pages;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class MyLeave extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'My Leave';

    protected static ?string $navigationGroup = 'Leave';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.my-leave';

    public int $year;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->employee_id;
    }

    /**
     * @return array<int, int>
     */
    public function yearOptions(): array
    {
        return collect(range(now()->year - 2, now()->year + 1))
            ->mapWithKeys(fn ($y) => [$y => $y])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => EmployeeLeaveBalance::query()
                ->where('employee_id', auth()->user()->employee_id)
                ->where('year', $this->year))
            ->columns([
                Tables\Columns\TextColumn::make('leaveType.name')->label('Leave Type'),
                Tables\Columns\TextColumn::make('opening_balance')->label('Opening'),
                Tables\Columns\TextColumn::make('credited')->label('Credited'),
                Tables\Columns\TextColumn::make('used')
                    ->label('Taken')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('closing_balance')
                    ->label('Available')
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->paginated(false);
    }

    /**
     * The employee's leave applications for the selected year — so "taken leave" is itemised.
     *
     * @return Collection<int, LeaveApplication>
     */
    public function myApplications(): Collection
    {
        return LeaveApplication::query()
            ->with('leaveType')
            ->where('employee_id', auth()->user()->employee_id)
            ->whereYear('from_date', $this->year)
            ->orderByDesc('from_date')
            ->limit(50)
            ->get();
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            LeaveApplication::STATUS_APPROVED => 'success',
            LeaveApplication::STATUS_REJECTED => 'danger',
            LeaveApplication::STATUS_SENT_BACK => 'warning',
            default => 'gray',
        };
    }
}
