<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrejoiningInviteResource\Pages;
use App\Models\Employee;
use App\Models\PrejoiningInvite;
use App\Services\EmployeeProfileService;
use App\Services\PrejoiningService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** HR view of the pre-joining portal: who was invited, how far they got, review / reopen. */
class PrejoiningInviteResource extends Resource
{
    protected static ?string $model = PrejoiningInvite::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Pre-joining';

    protected static ?string $modelLabel = 'pre-joining invite';

    protected static ?string $navigationGroup = 'Onboarding';

    protected static ?int $navigationSort = 0;

    private static function hr(): bool
    {
        return auth()->user()?->can('onboarding.manage') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::hr();
    }

    public static function canCreate(): bool
    {
        return false; // invites are sent with the "Invite New Hire" action
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::hr()) {
            return null;
        }

        $count = PrejoiningInvite::where('status', PrejoiningInvite::STATUS_SUBMITTED)->count();

        return $count ? (string) $count : null;
    }

    /** Shared "send link" action, used here and on the Employees / Onboarding lists. */
    public static function inviteAction(string $actionClass, bool $pickEmployee = false)
    {
        return $actionClass::make('prejoiningInvite')
            ->label($pickEmployee ? 'Invite New Hire' : 'Send Pre-joining Link')
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->visible(fn (?Model $record = null) => static::hr()
                && ($pickEmployee || ($record instanceof Employee && (! $record->date_of_joining || $record->date_of_joining->gte(today()->subDays(30))))))
            ->fillForm(fn (?Model $record = null) => $record instanceof Employee ? ['email' => $record->personal_email] : [])
            ->form(array_filter([
                $pickEmployee ? Forms\Components\Select::make('employee_id')
                    ->label('New hire')
                    ->options(fn () => Employee::query()
                        ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhere('date_of_joining', '>=', today()->subDays(30)->toDateString()))
                        ->orderBy('first_name')->get()
                        ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} - {$e->full_name}".($e->date_of_joining ? ' (joins '.$e->date_of_joining->format('d M').')' : '')]))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('email', Employee::find($state)?->personal_email))
                    ->helperText('Add the employee first (Employees → New) with their joining date.') : null,
                Forms\Components\TextInput::make('email')->label('Send to (personal email)')->email()->required(),
            ]))
            ->modalDescription('Emails the new hire a secure link (valid '.PrejoiningService::LINK_VALID_DAYS.' days) to fill in their personal, address, emergency, statutory and bank details and upload documents. Any earlier link stops working.')
            ->action(function (array $data, ?Model $record = null) {
                $employee = $record instanceof Employee ? $record : Employee::findOrFail($data['employee_id']);

                try {
                    app(PrejoiningService::class)->invite($employee, $data['email'], auth()->user());
                    Notification::make()->title('Pre-joining link sent to '.$data['email'])->success()->send();
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                }
            });
    }

    public static function table(Table $table): Table
    {
        $profile = app(EmployeeProfileService::class);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee.statutoryDetail', 'employee.bankDetails', 'employee.documents', 'sender']))
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')->label('New Hire')
                    ->description(fn (PrejoiningInvite $r) => $r->employee?->employee_code)
                    ->searchable(['first_name', 'middle_name', 'last_name']),
                Tables\Columns\TextColumn::make('employee.date_of_joining')->label('Joins')->date(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('completion')->label('Completed')
                    ->state(fn (PrejoiningInvite $r) => $r->employee ? $profile->completion($r->employee)['percent'].'%' : '—')
                    ->badge()
                    ->color(fn (string $state) => (int) $state >= 100 ? 'success' : ((int) $state >= 50 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PrejoiningInvite $r, string $state) => $state === PrejoiningInvite::STATUS_SENT && $r->expires_at->isPast()
                        ? 'Expired' : (PrejoiningInvite::STATUSES[$state] ?? $state))
                    ->color(fn (string $state) => match ($state) {
                        PrejoiningInvite::STATUS_SUBMITTED => 'warning',
                        PrejoiningInvite::STATUS_APPROVED => 'success',
                        PrejoiningInvite::STATUS_REVOKED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Sent')->since()->sortable(),
                Tables\Columns\TextColumn::make('last_accessed_at')->label('Last opened')->since()->placeholder('Not yet'),
                Tables\Columns\TextColumn::make('submitted_at')->label('Submitted')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PrejoiningInvite::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (PrejoiningInvite $r) => $r->employee ? EmployeeResource::getUrl('edit', ['record' => $r->employee]) : null),
                Tables\Actions\Action::make('approve')
                    ->label('Mark Reviewed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PrejoiningInvite $r) => $r->status === PrejoiningInvite::STATUS_SUBMITTED)
                    ->form([Forms\Components\Textarea::make('remarks')->label('Note (optional)')])
                    ->modalDescription('Check the details and documents on the employee record (Documents tab — tick Verified per document) before marking reviewed.')
                    ->action(fn (PrejoiningInvite $r, array $data) => static::run(
                        fn () => app(PrejoiningService::class)->approve($r, auth()->user(), $data['remarks'] ?? null), 'Marked reviewed')),
                Tables\Actions\Action::make('reopen')
                    ->label('Send Back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (PrejoiningInvite $r) => in_array($r->status, [PrejoiningInvite::STATUS_SUBMITTED, PrejoiningInvite::STATUS_APPROVED], true))
                    ->form([Forms\Components\Textarea::make('remarks')->label('What should they correct?')->required()])
                    ->action(fn (PrejoiningInvite $r, array $data) => static::run(
                        fn () => app(PrejoiningService::class)->reopen($r, auth()->user(), $data['remarks']), 'Sent back — the new hire has been emailed')),
                Tables\Actions\Action::make('resend')
                    ->label('Resend Link')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (PrejoiningInvite $r) => $r->status === PrejoiningInvite::STATUS_SENT)
                    ->requiresConfirmation()
                    ->modalDescription('Sends a fresh link; the old one stops working. Anything already filled in is kept.')
                    ->action(fn (PrejoiningInvite $r) => static::run(
                        fn () => app(PrejoiningService::class)->invite($r->employee, $r->email, auth()->user()), 'New link sent')),
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (PrejoiningInvite $r) => in_array($r->status, [PrejoiningInvite::STATUS_SENT, PrejoiningInvite::STATUS_SUBMITTED], true))
                    ->requiresConfirmation()
                    ->action(fn (PrejoiningInvite $r) => static::run(fn () => app(PrejoiningService::class)->revoke($r), 'Link revoked')),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrejoiningInvites::route('/'),
        ];
    }
}
