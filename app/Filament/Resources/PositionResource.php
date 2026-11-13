<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByFieldForce;
use App\Filament\Resources\PositionResource\Pages;
use App\Filament\Resources\PositionResource\RelationManagers\AssignmentsRelationManager;
use App\Models\Employee;
use App\Models\Geography;
use App\Models\HierarchyLevel;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Services\PositionAssignmentService;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PositionResource extends Resource
{
    use GatedByFieldForce;

    protected static ?string $model = Position::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Positions & Vacancies';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('hierarchy_level_id')
                    ->label('Hierarchy Level')
                    ->relationship('hierarchyLevel', 'name', fn (Builder $q) => $q->orderBy('rank'))
                    ->getOptionLabelFromRecordUsing(fn (HierarchyLevel $r) => "{$r->short_name} - {$r->name}")
                    ->preload()->required()->live(),
                Forms\Components\Select::make('division_id')
                    ->relationship('division', 'name')
                    ->preload(),
                Forms\Components\Select::make('geography_id')
                    ->label('Geography (HQ / Area / Region...)')
                    ->options(function (Forms\Get $get) {
                        $level = HierarchyLevel::find($get('hierarchy_level_id'));

                        return Geography::query()
                            ->with('level')
                            ->when($level?->geo_level_id, fn (Builder $q) => $q->where('geo_level_id', $level->geo_level_id))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Geography $g) => [$g->id => "{$g->name} ({$g->level?->name})"]);
                    })
                    ->searchable(),
                Forms\Components\Select::make('parent_position_id')
                    ->label('Reports to (position)')
                    ->options(function (Forms\Get $get, ?Position $record) {
                        $level = HierarchyLevel::find($get('hierarchy_level_id'));

                        return Position::query()
                            ->with(['hierarchyLevel', 'employee'])
                            ->when($level, fn (Builder $q) => $q->whereHas('hierarchyLevel', fn (Builder $h) => $h->where('rank', '<', $level->rank)))
                            ->when($record, fn (Builder $q) => $q->whereNotIn('id', $record->descendantAndSelfIds()))
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Position $p) => [$p->id => "{$p->code} - {$p->title} ({$p->hierarchyLevel?->short_name}"
                                .($p->employee ? ': '.$p->employee->full_name : ': VACANT').')']);
                    })
                    ->searchable()
                    ->rules(fn (?Position $record) => [
                        fn (string $attribute, $value, \Closure $fail) => $record?->wouldCreateCycle($value ? (int) $value : null)
                            ? $fail('A position cannot report to itself or to a position below it.')
                            : null,
                    ]),
                Forms\Components\TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText('e.g. MR-THN-CD-01'),
                Forms\Components\TextInput::make('title')->required()->maxLength(150)
                    ->helperText('e.g. MR - Thane HQ - Cardio'),
                Forms\Components\Select::make('company_id')
                    ->relationship('company', 'name', fn (Builder $q) => $q->where('has_field_force', true))
                    ->preload(),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Placeholder::make('incumbent')
                    ->label('Current incumbent')
                    ->content(fn (?Position $record) => $record?->employee
                        ? "{$record->employee->employee_code} - {$record->employee->full_name}"
                        : 'Vacant - use "Assign" on the positions list to fill this seat.')
                    ->visibleOn('edit'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('hierarchyLevel.short_name')->label('Level')->badge()->sortable(),
                Tables\Columns\TextColumn::make('division.name')->toggleable(),
                Tables\Columns\TextColumn::make('geography.name')->label('Geography')->searchable(),
                Tables\Columns\TextColumn::make('parent.title')->label('Reports to')->toggleable(),
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Incumbent')
                    ->default('VACANT')
                    ->badge(fn (Position $record) => $record->isVacant())
                    ->color(fn (Position $record) => $record->isVacant() ? 'danger' : null)
                    ->description(fn (Position $record) => $record->employee && ! in_array($record->employee->status, [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION], true)
                        ? 'Status: '.(Employee::STATUSES[$record->employee->status] ?? $record->employee->status)
                        : $record->employee?->employee_code)
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas('employee', fn (Builder $e) => $e
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('vacant_since')
                    ->label('Vacant for')
                    ->formatStateUsing(fn (Position $record) => $record->isVacant() && $record->vacant_since
                        ? $record->vacant_since->diffInDays(now()->startOfDay()).' days'
                        : null)
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hierarchy_level_id')->relationship('hierarchyLevel', 'name')->label('Level'),
                Tables\Filters\SelectFilter::make('division_id')->relationship('division', 'name')->label('Division'),
                Tables\Filters\TernaryFilter::make('vacant')
                    ->label('Vacancy')
                    ->trueLabel('Vacant only')
                    ->falseLabel('Filled only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('employee_id'),
                        false: fn (Builder $q) => $q->whereNotNull('employee_id'),
                    ),
                Tables\Filters\SelectFilter::make('geography')
                    ->label('Within geography')
                    ->options(fn () => Geography::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $q, array $data) => $data['value']
                        ? $q->whereIn('geography_id', Geography::find($data['value'])?->descendantAndSelfIds() ?? [])
                        : $q),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('assign')
                    ->label(fn (Position $record) => $record->isVacant() ? 'Assign' : 'Replace')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->can('fieldforce.manage') ?? false)
                    ->form([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->options(fn () => Employee::query()
                                ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
                                ->orderBy('employee_code')
                                ->get()
                                ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}"]))
                            ->searchable()
                            ->required()
                            ->helperText('If this employee holds another position today, that position becomes vacant (transfer).'),
                        Forms\Components\DatePicker::make('start_date')->default(now())->required(),
                        Forms\Components\Select::make('reason')->options(PositionAssignment::REASONS)->default('joining')->required(),
                        Forms\Components\TextInput::make('remarks')->maxLength(255),
                        Forms\Components\Toggle::make('sync_reporting')
                            ->label('Update reporting managers from this position tree')
                            ->helperText('Sets who this person reports to (and who reports to them) so leave/expense approvals follow the field hierarchy.')
                            ->default(true),
                    ])
                    ->action(function (Position $record, array $data) {
                        app(PositionAssignmentService::class)->assign(
                            $record,
                            Employee::findOrFail($data['employee_id']),
                            $data['start_date'],
                            $data['reason'],
                            $data['remarks'] ?? null,
                            (bool) ($data['sync_reporting'] ?? true),
                            auth()->id(),
                        );
                        Notification::make()->success()->title('Position assigned')->send();
                    }),
                Tables\Actions\Action::make('vacate')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (Position $record) => ! $record->isVacant() && (auth()->user()?->can('fieldforce.manage') ?? false))
                    ->form([
                        Forms\Components\DatePicker::make('last_date')->label('Last day in position')->default(now())->required(),
                        Forms\Components\Select::make('reason')->options(PositionAssignment::REASONS)->default('exit')->required(),
                        Forms\Components\Toggle::make('sync_reporting')
                            ->label('Re-point the team to the next manager up')
                            ->default(true),
                    ])
                    ->action(function (Position $record, array $data) {
                        app(PositionAssignmentService::class)->vacate($record, $data['last_date'], $data['reason'], (bool) ($data['sync_reporting'] ?? true));
                        Notification::make()->success()->title('Position marked vacant')->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function listHeaderActions(): array
    {
        return [
            PageAction::make('syncReporting')
                ->label('Sync reporting managers')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('fieldforce.manage') ?? false)
                ->requiresConfirmation()
                ->modalDescription('Sets each position holder\'s reporting manager to the holder of the nearest filled position above them. Head-office employees without a position are not touched.')
                ->action(function () {
                    $changed = app(PositionAssignmentService::class)->syncAll();
                    Notification::make()->success()->title("{$changed} reporting manager(s) updated")->send();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [AssignmentsRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['hierarchyLevel', 'division', 'geography', 'parent', 'employee']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPositions::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'edit' => Pages\EditPosition::route('/{record}/edit'),
        ];
    }
}
