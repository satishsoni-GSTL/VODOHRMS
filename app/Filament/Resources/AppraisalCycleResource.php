<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppraisalCycleResource\Pages;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Services\AppraisalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** HR runs appraisal cycles: create → Launch (goal setting) → Start Review → Close. */
class AppraisalCycleResource extends Resource
{
    protected static ?string $model = AppraisalCycle::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Appraisal Cycles';

    protected static ?string $navigationGroup = 'Performance';

    protected static ?int $navigationSort = 2;

    private static function hr(): bool
    {
        return auth()->user()?->can('employee.edit') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::hr();
    }

    public static function canCreate(): bool
    {
        return static::hr();
    }

    public static function canEdit(Model $record): bool
    {
        return static::hr() && $record->status !== AppraisalCycle::STATUS_CLOSED;
    }

    public static function canDelete(Model $record): bool
    {
        return static::hr() && $record->status === AppraisalCycle::STATUS_DRAFT;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(120)->placeholder('FY 2026-27 Annual Appraisal'),
                Forms\Components\Select::make('company_id')->label('Company')->relationship('company', 'name')->placeholder('All companies'),
                Forms\Components\DatePicker::make('period_start')->label('Review period from')->required(),
                Forms\Components\DatePicker::make('period_end')->label('Review period to')->required()->afterOrEqual('period_start'),
                Forms\Components\DatePicker::make('goal_setting_due')->label('Goals due by'),
                Forms\Components\DatePicker::make('review_due')->label('Reviews due by'),
                Forms\Components\TextInput::make('min_goals')->label('Minimum goals per employee')->numeric()->integer()->minValue(1)->maxValue(15)->default(3)->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('company.name')->placeholder('All'),
                Tables\Columns\TextColumn::make('period_start')->label('From')->date(),
                Tables\Columns\TextColumn::make('period_end')->label('To')->date(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => AppraisalCycle::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        AppraisalCycle::STATUS_GOAL_SETTING => 'warning',
                        AppraisalCycle::STATUS_REVIEW => 'info',
                        AppraisalCycle::STATUS_CLOSED => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('appraisals_count')->label('Employees')->counts('appraisals'),
                Tables\Columns\TextColumn::make('progress')->label('Progress')
                    ->state(function (AppraisalCycle $record) {
                        $counts = $record->appraisals()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
                        $goals = $counts->except([Appraisal::STATUS_GOALS_DRAFT, Appraisal::STATUS_GOALS_SUBMITTED])->sum();
                        $reviewed = $counts->only([Appraisal::STATUS_MANAGER_SUBMITTED, Appraisal::STATUS_CLOSED])->sum();

                        return "Goals approved {$goals} · Reviewed {$reviewed}";
                    }),
            ])
            ->defaultSort('period_start', 'desc')
            ->actions([
                Tables\Actions\Action::make('launch')
                    ->label('Launch')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->visible(fn (AppraisalCycle $record) => $record->status === AppraisalCycle::STATUS_DRAFT)
                    ->requiresConfirmation()
                    ->modalDescription('Creates an appraisal for every active employee in scope (reviewer = their reporting manager) and asks them to set goals.')
                    ->action(fn (AppraisalCycle $record) => static::run(
                        fn () => app(AppraisalService::class)->launch($record),
                        fn ($n) => "Cycle launched for {$n} employees",
                    )),
                Tables\Actions\Action::make('startReview')
                    ->label('Start Review')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (AppraisalCycle $record) => $record->status === AppraisalCycle::STATUS_GOAL_SETTING)
                    ->requiresConfirmation()
                    ->modalDescription('Opens self reviews for everyone whose goals are approved. Goals still pending can be approved later and joined in.')
                    ->action(fn (AppraisalCycle $record) => static::run(
                        fn () => app(AppraisalService::class)->startReview($record),
                        fn () => 'Review phase started',
                    )),
                Tables\Actions\Action::make('close')
                    ->label('Close Cycle')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->visible(fn (AppraisalCycle $record) => $record->status === AppraisalCycle::STATUS_REVIEW)
                    ->requiresConfirmation()
                    ->modalDescription('Finalises every manager-reviewed appraisal. Unfinished appraisals stay incomplete. This cannot be undone.')
                    ->action(fn (AppraisalCycle $record) => static::run(
                        fn () => app(AppraisalService::class)->closeCycle($record),
                        fn ($n) => "Cycle closed · {$n} appraisals finalised",
                    )),
                Tables\Actions\Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (AppraisalCycle $record) => $record->status !== AppraisalCycle::STATUS_DRAFT)
                    ->url(fn (AppraisalCycle $record) => route('reports.download', ['type' => 'appraisal', 'cycle' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('appraisals')
                    ->label('Appraisals')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->url(fn (AppraisalCycle $record) => AppraisalResource::getUrl('index', ['tableFilters[appraisal_cycle_id][value]' => $record->id])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    private static function run(callable $callback, callable $success): void
    {
        try {
            $result = $callback();
            Notification::make()->title($success($result))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisalCycles::route('/'),
            'create' => Pages\CreateAppraisalCycle::route('/create'),
            'edit' => Pages\EditAppraisalCycle::route('/{record}/edit'),
        ];
    }
}
