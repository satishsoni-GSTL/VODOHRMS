<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExitClearanceResource\Pages;
use App\Models\ExitClearance;
use App\Services\ExitClearanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExitClearanceResource extends Resource
{
    protected static ?string $model = ExitClearance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Exit Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('remarks')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('resignation.employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('resignation.employee.full_name')->label('Employee')->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('department')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ExitClearance::STATUS_CLEARED => 'success',
                        ExitClearance::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('remarks')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('clearedBy.name')->label('Cleared By')->placeholder('—'),
                Tables\Columns\TextColumn::make('cleared_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    ExitClearance::STATUS_PENDING => 'Pending',
                    ExitClearance::STATUS_CLEARED => 'Cleared',
                    ExitClearance::STATUS_REJECTED => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('department')->options(array_combine(ExitClearance::DEPARTMENTS, array_map('ucfirst', ExitClearance::DEPARTMENTS))),
            ])
            ->actions([
                Tables\Actions\Action::make('clear')
                    ->label('Clear')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ExitClearance $record) => $record->status === ExitClearance::STATUS_PENDING && auth()->user()->can('exit.manage'))
                    ->form([Forms\Components\Textarea::make('remarks')])
                    ->action(function (ExitClearance $record, array $data) {
                        app(ExitClearanceService::class)->clear($record, auth()->user(), $data['remarks'] ?? null);
                        Notification::make()->title('Clearance marked cleared')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ExitClearance $record) => $record->status === ExitClearance::STATUS_PENDING && auth()->user()->can('exit.manage'))
                    ->form([Forms\Components\Textarea::make('remarks')->required()])
                    ->action(function (ExitClearance $record, array $data) {
                        app(ExitClearanceService::class)->reject($record, auth()->user(), $data['remarks']);
                        Notification::make()->title('Clearance rejected')->danger()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->can('exit.manage') || $user->can('resignation.view')) {
            return $query;
        }

        return $query->whereHas('resignation', fn (Builder $q) => $q->where('employee_id', $user->employee_id));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExitClearances::route('/'),
        ];
    }
}
