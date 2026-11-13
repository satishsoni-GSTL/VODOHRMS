<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnboardingTaskResource\Pages;
use App\Models\Employee;
use App\Models\OnboardingTask;
use App\Models\OnboardingTaskTemplate;
use App\Services\OnboardingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Every onboarding task. HR (onboarding.manage) sees all of them and can add, skip or
 * reopen; everyone else sees the tasks they own (including a new joiner's own tasks) and
 * can mark them done.
 */
class OnboardingTaskResource extends Resource
{
    protected static ?string $model = OnboardingTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationLabel = 'Onboarding Tasks';

    protected static ?string $navigationGroup = 'Onboarding';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && ($user->can('onboarding.manage') || OnboardingTask::query()->assignedTo($user)->exists());
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('onboarding.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('onboarding.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('onboarding.manage') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $count = OnboardingTask::query()->assignedTo($user)->where('status', OnboardingTask::STATUS_PENDING)
            ->whereDate('due_date', '<=', today()->addDays(3))->count();

        return $count ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('New joiner')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name'])
                    ->required(),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('assigned_user_id')
                    ->label('Owner (person)')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload()
                    ->requiredWithout('role_id'),
                Forms\Components\Select::make('role_id')
                    ->label('Or owner (anyone with role)')
                    ->relationship('role', 'name'),
                Forms\Components\DatePicker::make('due_date')->required(),
                Forms\Components\Hidden::make('assignee_type')->default(OnboardingTaskTemplate::ASSIGNEE_USER),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')->label('New Joiner')
                    ->description(fn (OnboardingTask $record) => $record->employee?->employee_code)
                    ->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('title')->wrap()->searchable()
                    ->description(fn (OnboardingTask $record) => $record->description ? \Illuminate\Support\Str::limit($record->description, 80) : null),
                Tables\Columns\TextColumn::make('owner_label')->label('Owner'),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable()
                    ->color(fn (OnboardingTask $record) => $record->isOverdue() ? 'danger' : null),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OnboardingTask $record, string $state) => $record->isOverdue() ? 'Overdue' : (OnboardingTask::STATUSES[$state] ?? $state))
                    ->color(fn (OnboardingTask $record, string $state) => match (true) {
                        $record->isOverdue() => 'danger',
                        $state === OnboardingTask::STATUS_DONE => 'success',
                        $state === OnboardingTask::STATUS_SKIPPED => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('completedBy.name')->label('Closed By')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('remarks')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('due_date')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(OnboardingTask::STATUSES)->default(OnboardingTask::STATUS_PENDING),
                Tables\Filters\SelectFilter::make('employee_id')->label('New joiner')
                    ->options(fn () => Employee::query()->whereHas('onboardingTasks')->get()
                        ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}"]))
                    ->searchable(),
                Tables\Filters\Filter::make('mine')->label('Only my tasks')
                    ->query(fn (Builder $query) => $query->assignedTo(auth()->user())),
                Tables\Filters\Filter::make('overdue')
                    ->query(fn (Builder $query) => $query->where('status', OnboardingTask::STATUS_PENDING)->whereDate('due_date', '<', today())),
            ])
            ->actions([
                Tables\Actions\Action::make('done')
                    ->label('Mark Done')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (OnboardingTask $record) => $record->status === OnboardingTask::STATUS_PENDING
                        && app(OnboardingService::class)->canAct($record, auth()->user()))
                    ->form([Forms\Components\Textarea::make('remarks')->label('Remarks (optional)')->rows(2)])
                    ->action(fn (OnboardingTask $record, array $data) => static::run(
                        fn () => app(OnboardingService::class)->complete($record, auth()->user(), $data['remarks'] ?? null),
                        'Task marked done',
                    )),
                Tables\Actions\Action::make('skip')
                    ->label('Skip')
                    ->icon('heroicon-o-forward')
                    ->color('gray')
                    ->visible(fn (OnboardingTask $record) => $record->status === OnboardingTask::STATUS_PENDING && auth()->user()->can('onboarding.manage'))
                    ->form([Forms\Components\Textarea::make('remarks')->label('Why is this not needed?')->required()->rows(2)])
                    ->action(fn (OnboardingTask $record, array $data) => static::run(
                        fn () => app(OnboardingService::class)->skip($record, auth()->user(), $data['remarks']),
                        'Task skipped',
                    )),
                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (OnboardingTask $record) => $record->status !== OnboardingTask::STATUS_PENDING && auth()->user()->can('onboarding.manage'))
                    ->requiresConfirmation()
                    ->action(fn (OnboardingTask $record) => static::run(fn () => app(OnboardingService::class)->reopen($record), 'Task reopened')),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    private static function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee', 'assignedUser', 'role', 'completedBy']);
        $user = auth()->user();

        return $user?->can('onboarding.manage') ? $query : $query->assignedTo($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnboardingTasks::route('/'),
            'create' => Pages\CreateOnboardingTask::route('/create'),
            'edit' => Pages\EditOnboardingTask::route('/{record}/edit'),
        ];
    }
}
