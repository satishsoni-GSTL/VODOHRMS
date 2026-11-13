<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppraisalResource\Pages;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\User;
use App\Services\AppraisalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One appraisal per employee per cycle. Employees see their own; managers see their
 * reporting line; HR (employee.edit) sees everyone. The edit page is the working screen for
 * every stage — what is editable is decided by AppraisalService for the current user.
 */
class AppraisalResource extends Resource
{
    protected static ?string $model = Appraisal::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Appraisals';

    protected static ?string $navigationGroup = 'Performance';

    protected static ?int $navigationSort = 1;

    private static function svc(): AppraisalService
    {
        return app(AppraisalService::class);
    }

    private static function user(): ?User
    {
        return auth()->user();
    }

    public static function canViewAny(): bool
    {
        $user = static::user();

        return $user && (static::svc()->isHr($user) || ($user->employee_id && static::scoped(Appraisal::query(), $user)->exists()));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** "Edit" is the working screen; anyone who can see the appraisal may open it. */
    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = static::user();

        if (! $user?->employee_id) {
            return null;
        }

        // Things waiting on me: my own goals / self review, or my team's approvals / reviews.
        $count = Appraisal::query()
            ->whereHas('cycle', fn ($q) => $q->whereIn('status', [AppraisalCycle::STATUS_GOAL_SETTING, AppraisalCycle::STATUS_REVIEW]))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('employee_id', $user->employee_id)->where(fn ($q) => $q
                    ->where('status', Appraisal::STATUS_GOALS_DRAFT)
                    ->orWhere(fn ($q) => $q->where('status', Appraisal::STATUS_GOALS_APPROVED)
                        ->whereHas('cycle', fn ($q) => $q->where('status', AppraisalCycle::STATUS_REVIEW)))))
                ->orWhere(fn ($q) => $q->where('manager_id', $user->employee_id)
                    ->whereIn('status', [Appraisal::STATUS_GOALS_SUBMITTED, Appraisal::STATUS_SELF_SUBMITTED])))
            ->count();

        return $count ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        $svc = static::svc();
        $user = static::user();

        $can = fn (string $check) => fn ($livewire) => $livewire->record && $svc->{$check}($livewire->record, $user);
        $cannot = fn (string $check) => fn ($livewire) => ! ($livewire->record && $svc->{$check}($livewire->record, $user));

        // Self ratings appear once goals are approved; manager ratings once the self review is
        // in — and, to the employee, only after the manager has submitted.
        $showSelf = fn ($livewire) => $livewire->record
            && ! in_array($livewire->record->status, [Appraisal::STATUS_GOALS_DRAFT, Appraisal::STATUS_GOALS_SUBMITTED], true)
            && ($livewire->record->cycle->status !== AppraisalCycle::STATUS_GOAL_SETTING);
        $showManager = fn ($livewire) => $livewire->record
            && in_array($livewire->record->status, [Appraisal::STATUS_SELF_SUBMITTED, Appraisal::STATUS_MANAGER_SUBMITTED, Appraisal::STATUS_CLOSED], true)
            && (! $svc->isEmployee($livewire->record, $user) || $livewire->record->managerReviewVisibleToEmployee());

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('employee_info')->label('Employee')
                            ->content(fn ($livewire) => $livewire->record?->employee ? "{$livewire->record->employee->full_name} ({$livewire->record->employee->employee_code})" : '—'),
                        Forms\Components\Placeholder::make('manager_info')->label('Reviewer')
                            ->content(fn ($livewire) => $livewire->record?->manager?->full_name ?? 'Not set — HR will review'),
                        Forms\Components\Placeholder::make('cycle_info')->label('Cycle')
                            ->content(fn ($livewire) => $livewire->record?->cycle
                                ? $livewire->record->cycle->name.' · '.(AppraisalCycle::STATUSES[$livewire->record->cycle->status] ?? '')
                                : '—'),
                        Forms\Components\Placeholder::make('status_info')->label('Stage')
                            ->content(fn ($livewire) => Appraisal::STATUSES[$livewire->record?->status] ?? '—'),
                        Forms\Components\Placeholder::make('returned')->label('Goals returned by manager')
                            ->content(fn ($livewire) => $livewire->record?->goals_return_remarks)
                            ->visible(fn ($livewire) => filled($livewire->record?->goals_return_remarks) && $livewire->record->status === Appraisal::STATUS_GOALS_DRAFT)
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Goals (KRAs)')
                    ->description(fn (Get $get) => 'Total weightage: '.collect($get('goals') ?? [])->sum(fn ($g) => (int) ($g['weightage'] ?? 0)).'% (must be 100%)')
                    ->schema([
                        Forms\Components\Repeater::make('goals')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('title')->label('Goal')->required()->maxLength(255)->columnSpan(3)
                                    ->disabled($cannot('canEditGoals')),
                                Forms\Components\TextInput::make('weightage')->label('Weight %')->numeric()->integer()->minValue(1)->maxValue(100)->required()->live(onBlur: true)
                                    ->disabled($cannot('canEditGoals')),
                                Forms\Components\TextInput::make('target')->label('Target / measure')->maxLength(255)->columnSpan(2)
                                    ->disabled($cannot('canEditGoals')),
                                Forms\Components\Textarea::make('description')->rows(1)->columnSpan(2)
                                    ->disabled($cannot('canEditGoals')),
                                Forms\Components\Select::make('self_rating')->label('Self rating')->options(Appraisal::RATINGS)
                                    ->visible($showSelf)->disabled($cannot('canSelfReview'))
                                    ->required($can('canSelfReview')),
                                Forms\Components\Textarea::make('self_comments')->label('Self comments')->rows(1)->columnSpan(3)
                                    ->visible($showSelf)->disabled($cannot('canSelfReview')),
                                Forms\Components\Select::make('manager_rating')->label('Manager rating')->options(Appraisal::RATINGS)
                                    ->visible($showManager)->disabled($cannot('canManagerReview'))
                                    ->required($can('canManagerReview')),
                                Forms\Components\Textarea::make('manager_comments')->label('Manager comments')->rows(1)->columnSpan(3)
                                    ->visible($showManager)->disabled($cannot('canManagerReview')),
                            ])
                            ->columns(4)
                            ->addable($can('canEditGoals'))
                            ->deletable($can('canEditGoals'))
                            ->reorderable(false)
                            ->addActionLabel('Add goal')
                            ->itemLabel(fn (array $state) => $state['title'] ?? null)
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make('Overall')
                    ->schema([
                        Forms\Components\Textarea::make('self_overall_comments')->label('My overall comments')->rows(3)
                            ->visible($showSelf)->disabled($cannot('canSelfReview')),
                        Forms\Components\Textarea::make('manager_overall_comments')->label('Manager overall comments')->rows(3)
                            ->visible($showManager)->disabled($cannot('canManagerReview')),
                        Forms\Components\Placeholder::make('scores')->label('Scores')
                            ->content(fn ($livewire) => collect([
                                'Self' => Appraisal::ratingLabel($livewire->record?->self_score),
                                'Manager' => Appraisal::ratingLabel($livewire->record?->manager_score),
                                'Final' => Appraisal::ratingLabel($livewire->record?->final_rating),
                            ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode('  ·  ') ?: '—')
                            ->visible($showManager),
                        Forms\Components\Placeholder::make('final_remarks_info')->label('HR calibration note')
                            ->content(fn ($livewire) => $livewire->record?->final_remarks)
                            ->visible(fn ($livewire) => filled($livewire->record?->final_remarks) && $showManager($livewire)),
                    ])
                    ->columns(2)
                    ->visible($showSelf),
            ]);
    }

    public static function table(Table $table): Table
    {
        $svc = static::svc();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cycle.name')->label('Cycle')->sortable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')
                    ->description(fn (Appraisal $record) => $record->employee?->employee_code)
                    ->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('manager.full_name')->label('Reviewer')->placeholder('HR'),
                Tables\Columns\TextColumn::make('goals_count')->label('Goals')->counts('goals'),
                Tables\Columns\TextColumn::make('status')->label('Stage')->badge()
                    ->formatStateUsing(fn (string $state) => Appraisal::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Appraisal::STATUS_GOALS_DRAFT => 'gray',
                        Appraisal::STATUS_GOALS_SUBMITTED, Appraisal::STATUS_SELF_SUBMITTED => 'warning',
                        Appraisal::STATUS_GOALS_APPROVED => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('final_rating')->label('Rating')
                    ->state(fn (Appraisal $record) => $svc->isEmployee($record, static::user()) && ! $record->managerReviewVisibleToEmployee()
                        ? null
                        : Appraisal::ratingLabel($record->final_rating))
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('appraisal_cycle_id')->label('Cycle')->relationship('cycle', 'name'),
                Tables\Filters\SelectFilter::make('status')->label('Stage')->options(Appraisal::STATUSES),
                Tables\Filters\Filter::make('mine')->label('My own appraisal')
                    ->query(fn (Builder $q) => $q->where('employee_id', static::user()?->employee_id)),
                Tables\Filters\Filter::make('to_review')->label('Waiting on me as reviewer')
                    ->query(fn (Builder $q) => $q->where('manager_id', static::user()?->employee_id)
                        ->whereIn('status', [Appraisal::STATUS_GOALS_SUBMITTED, Appraisal::STATUS_SELF_SUBMITTED])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Open')->icon('heroicon-o-arrow-top-right-on-square'),
            ])
            ->bulkActions([]);
    }

    /** HR: everyone. Others: their own appraisal, ones they review, and their reporting line's. */
    public static function scoped(Builder $query, User $user): Builder
    {
        if (static::svc()->isHr($user)) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q
            ->where('employee_id', $employee->id)
            ->orWhere('manager_id', $employee->id)
            ->orWhereIn('employee_id', $employee->allSubordinateIds()));
    }

    public static function getEloquentQuery(): Builder
    {
        $user = static::user();
        $query = parent::getEloquentQuery()->with(['cycle', 'employee', 'manager']);

        return $user ? static::scoped($query, $user) : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisals::route('/'),
            'edit' => Pages\EditAppraisal::route('/{record}/edit'),
        ];
    }
}
