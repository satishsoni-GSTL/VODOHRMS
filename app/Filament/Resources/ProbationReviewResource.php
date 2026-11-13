<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasApprovalActions;
use App\Filament\Resources\ProbationReviewResource\Pages;
use App\Models\Employee;
use App\Models\ProbationReview;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Probation confirmation reviews. The reporting manager (or HR) recommends Confirm / Extend;
 * the review then goes through the approval workflow (default: HR). Visible to HR (all) and
 * to managers (their reporting line) — the employee under review never sees the review
 * itself, only the final letter.
 */
class ProbationReviewResource extends Resource
{
    use HasApprovalActions;

    protected static ?string $model = ProbationReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Probation Reviews';

    protected static ?string $navigationGroup = 'Employees';

    protected static ?int $navigationSort = 6;

    /** Employee ids the user may review; null = everyone (HR). */
    public static function reviewableEmployeeIds(?User $user = null): ?array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        // HR tier (HR Admin / HR Executive). Not employee.view — Payroll / Finance hold that too.
        if ($user->can('employee.edit')) {
            return null;
        }

        return $user->employee?->allSubordinateIds() ?? [];
    }

    public static function canAccess(): bool
    {
        $ids = static::reviewableEmployeeIds();

        return $ids === null || $ids !== [];
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->options(function () {
                        $ids = static::reviewableEmployeeIds();

                        return Employee::query()
                            ->where('status', Employee::STATUS_PROBATION)
                            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
                            ->orderBy('employee_code')
                            ->get()
                            ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}"]);
                    })
                    ->searchable()
                    ->required()
                    ->live(),
                Forms\Components\Placeholder::make('probation_info')
                    ->label('Probation')
                    ->content(function (Get $get) {
                        $employee = $get('employee_id') ? Employee::find($get('employee_id')) : null;

                        if (! $employee?->date_of_joining) {
                            return '—';
                        }

                        return sprintf(
                            'Joined %s · %d-day probation · ends %s',
                            $employee->date_of_joining->format('d M Y'),
                            $employee->probation_period_days ?? Employee::DEFAULT_PROBATION_DAYS,
                            $employee->probationEndDate()->format('d M Y'),
                        );
                    }),
                Forms\Components\Radio::make('recommendation')
                    ->options(ProbationReview::RECOMMENDATIONS)
                    ->default(ProbationReview::RECOMMEND_CONFIRM)
                    ->inline()
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('extension_days')
                    ->label('Extend by (days)')
                    ->numeric()->integer()->minValue(1)->maxValue(365)
                    ->default(90)
                    ->visible(fn (Get $get) => $get('recommendation') === ProbationReview::RECOMMEND_EXTEND)
                    ->required(fn (Get $get) => $get('recommendation') === ProbationReview::RECOMMEND_EXTEND),
                Forms\Components\DatePicker::make('effective_date')
                    ->label('Confirm with effect from')
                    ->helperText('Leave empty to confirm from the probation end date.')
                    ->visible(fn (Get $get) => $get('recommendation') === ProbationReview::RECOMMEND_CONFIRM),
                Forms\Components\Select::make('rating')
                    ->label('Overall rating')
                    ->options(ProbationReview::RATINGS),
                Forms\Components\Textarea::make('performance_summary')
                    ->label('Performance during probation')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('improvement_areas')
                    ->label('Areas for improvement')
                    ->helperText('Shown in the extension letter.')
                    ->rows(3)
                    ->required(fn (Get $get) => $get('recommendation') === ProbationReview::RECOMMEND_EXTEND)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('probation_end_date')->label('Probation Ends')->date()->sortable(),
                Tables\Columns\TextColumn::make('recommendation_label')->label('Recommendation'),
                Tables\Columns\TextColumn::make('effective_date')->label('Effective')->date()->sortable(),
                Tables\Columns\TextColumn::make('rating')->formatStateUsing(fn ($state) => $state ? "{$state}/5" : null)->placeholder('—'),
                Tables\Columns\TextColumn::make('submitter.name')->label('Submitted By')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ProbationReview::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ProbationReview::STATUS_APPROVED => 'success',
                        ProbationReview::STATUS_REJECTED => 'danger',
                        ProbationReview::STATUS_SENT_BACK => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ProbationReview::STATUSES),
                Tables\Filters\SelectFilter::make('recommendation')->options(ProbationReview::RECOMMENDATIONS),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->extraModalFooterActions(fn () => static::approvalActions()),
                ...static::approvalActions(),
                static::letterAction(),
                static::reapplyAction(),
            ])
            ->bulkActions([]);
    }

    public static function letterAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('letter')
            ->label('Letter')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(fn (ProbationReview $record) => (bool) $record->letter_path)
            ->url(fn (ProbationReview $record) => route('probation-letters.download', $record))
            ->openUrlInNewTab();
    }

    /** Only the submitter or HR may resubmit a sent-back review — never the employee under review. */
    public static function canReapply(Model $record): bool
    {
        $user = auth()->user();

        return $user
            && $record->status === ProbationReview::STATUS_SENT_BACK
            && (int) $record->employee_id !== (int) $user->employee_id
            && ((int) $record->submitted_by === (int) $user->id
                || app(ApprovalWorkflowService::class)->userCanManageModule($record->getApprovalModule(), $user));
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = static::reviewableEmployeeIds();

        return parent::getEloquentQuery()
            ->with(['employee', 'submitter', 'approvalInstance'])
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('employee_id', $ids));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProbationReviews::route('/'),
            'create' => Pages\CreateProbationReview::route('/create'),
            'view' => Pages\ViewProbationReview::route('/{record}'),
        ];
    }
}
