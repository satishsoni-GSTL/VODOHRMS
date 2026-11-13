<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProbationReviewResource;
use App\Models\Employee;
use App\Models\ProbationReview;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Everyone currently on probation (HR: all; managers: their reporting line), with the
 * probation end date, days left and review status, and a one-click "Start review".
 */
class ProbationDue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Probation Due';

    protected static ?string $title = 'Probation Due';

    protected static ?string $navigationGroup = 'Employees';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.probation-due';

    public static function canAccess(): bool
    {
        return ProbationReviewResource::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $ids = ProbationReviewResource::reviewableEmployeeIds();

        if ($ids === []) {
            return null;
        }

        $count = app(\App\Services\ProbationService::class)->dueEmployees(30, $ids)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        $ids = ProbationReviewResource::reviewableEmployeeIds();

        return $table
            ->query(fn () => Employee::query()
                ->with(['reportingManager', 'designation', 'department'])
                ->withCount(['probationReviews as pending_reviews_count' => fn ($q) => $q->where('status', ProbationReview::STATUS_PENDING)])
                ->where('status', Employee::STATUS_PROBATION)
                ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids)))
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('designation.name')->label('Designation')->toggleable(),
                Tables\Columns\TextColumn::make('reportingManager.full_name')->label('Reporting Manager')->toggleable(),
                Tables\Columns\TextColumn::make('date_of_joining')->label('Joined')->date()->sortable(),
                Tables\Columns\TextColumn::make('probation_end')
                    ->label('Probation Ends')
                    ->state(fn (Employee $record) => $record->probationEndDate()?->format('d M Y') ?? '—'),
                Tables\Columns\TextColumn::make('days_left')
                    ->label('Days Left')
                    ->badge()
                    ->state(function (Employee $record) {
                        $end = $record->probationEndDate();

                        if (! $end) {
                            return 'No joining date';
                        }

                        $days = (int) Carbon::today()->diffInDays($end, false);

                        return $days < 0 ? abs($days).' overdue' : (string) $days;
                    })
                    ->color(function (Employee $record) {
                        $end = $record->probationEndDate();
                        $days = $end ? (int) Carbon::today()->diffInDays($end, false) : null;

                        return match (true) {
                            $days === null => 'gray',
                            $days < 0 => 'danger',
                            $days <= 15 => 'warning',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('review_status')
                    ->label('Review')
                    ->badge()
                    ->state(fn (Employee $record) => $record->pending_reviews_count ? 'Awaiting approval' : 'Not started')
                    ->color(fn (Employee $record) => $record->pending_reviews_count ? 'info' : 'gray'),
            ])
            // Earliest joiners first ~ earliest probation end (exact for a uniform period).
            ->defaultSort('date_of_joining')
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Start review')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Employee $record) => ! $record->pending_reviews_count && $record->date_of_joining)
                    ->url(fn (Employee $record) => ProbationReviewResource::getUrl('create', ['employee' => $record->id])),
            ])
            ->emptyStateHeading('Nobody is on probation')
            ->emptyStateDescription('Employees with status "Probation" appear here.');
    }
}
