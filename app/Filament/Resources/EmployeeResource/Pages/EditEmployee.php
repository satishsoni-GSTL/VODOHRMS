<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\PasswordResetByAdminNotification;
use App\Notifications\WelcomeAccountNotification;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Str;
use Throwable;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * The account change (new login, new password) has already been saved by the time we
     * notify — a broken mail transport must surface as a warning, not blow up the action after
     * the data change already succeeded.
     */
    private function notifyOrWarn(User $user, BaseNotification $notification, string $successBody): void
    {
        try {
            $user->notify($notification);

            Notification::make()->title('Done')->body($successBody)->success()->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Saved, but the email could not be sent')
                ->body("The account change was saved, but delivering the email to {$user->email} failed. Share the credentials with the employee another way, or fix mail delivery and use Reset Password to resend.")
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createLogin')
                ->label('Create Login')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This generates a temporary password and emails it to the employee. They will be required to change it on first login.')
                ->visible(fn () => auth()->user()?->can('employee.edit') && ! $this->record->user()->exists())
                ->action(function () {
                    $employee = $this->record;
                    $email = $employee->official_email ?: $employee->personal_email;

                    if (! $email) {
                        Notification::make()
                            ->title('No email on file')
                            ->body('Add a personal or official email address for this employee before creating a login.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $existing = User::where('email', $email)->first();

                    if ($existing) {
                        $linkedEmployee = $existing->employee_id ? Employee::withTrashed()->find($existing->employee_id) : null;
                        $orphaned = ! $linkedEmployee || $linkedEmployee->trashed();

                        if (! $orphaned) {
                            Notification::make()
                                ->title('Email already in use')
                                ->body("The email {$email} is already used by the login for {$linkedEmployee->employee_code} - {$linkedEmployee->full_name}. Use a different official/personal email for this employee, or resolve it on that account first.")
                                ->danger()
                                ->send();

                            return;
                        }

                        // The existing account's employee record was deleted (or it was never linked) —
                        // this is the "email already exists" dead end admins hit; retrieve and relink it
                        // instead of failing, so the employee gets a working login again.
                        $tempPassword = Str::password(14);

                        $existing->update([
                            'employee_id' => $employee->id,
                            'employee_code' => $employee->employee_code,
                            'name' => $employee->full_name,
                            'password' => $tempPassword,
                            'must_change_password' => true,
                            'is_active' => true,
                        ]);

                        $this->notifyOrWarn(
                            $existing,
                            new WelcomeAccountNotification($existing, $tempPassword),
                            "A previous login for {$email} was found (linked to a deleted employee record), reactivated, and relinked to this employee. New temporary credentials were emailed.",
                        );

                        return;
                    }

                    $tempPassword = Str::password(14);

                    $user = User::create([
                        'employee_id' => $employee->id,
                        'employee_code' => $employee->employee_code,
                        'name' => $employee->full_name,
                        'email' => $email,
                        'password' => $tempPassword,
                        'must_change_password' => true,
                        'is_active' => true,
                    ]);

                    $this->notifyOrWarn(
                        $user,
                        new WelcomeAccountNotification($user, $tempPassword),
                        "Login created. Welcome email with temporary credentials sent to {$email}.",
                    );
                }),
            Actions\Action::make('resetPassword')
                ->label('Reset Password')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('users.manage') && $this->record->user()->exists())
                ->form([
                    Forms\Components\Radio::make('mode')
                        ->label('New password')
                        ->options([
                            'auto' => 'Auto-generate a password',
                            'manual' => 'Set a specific password',
                        ])
                        ->default('auto')
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('new_password')
                        ->label('New password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (Get $get) => $get('mode') === 'manual')
                        ->visible(fn (Get $get) => $get('mode') === 'manual'),
                    Forms\Components\Toggle::make('must_change_password')
                        ->label('Require password change on next login')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $user = $this->record->user;

                    if (! $user) {
                        Notification::make()
                            ->title('No login found')
                            ->body('This employee does not have a login to reset.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $plainPassword = $data['mode'] === 'auto' ? Str::password(14) : $data['new_password'];

                    $user->update([
                        'password' => $plainPassword,
                        'must_change_password' => $data['must_change_password'],
                    ]);

                    app(AuditLogService::class)->log(
                        'password_reset',
                        $user,
                        newValues: ['must_change_password' => $data['must_change_password']],
                        module: 'User',
                    );

                    $this->notifyOrWarn(
                        $user,
                        new PasswordResetByAdminNotification($user, $plainPassword),
                        "Password reset. New password emailed to {$user->email}.",
                    );
                }),
            Actions\Action::make('deactivateLogin')
                ->label('Disable Login')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('The employee will no longer be able to sign in. This can be undone at any time with "Enable Login".')
                ->visible(fn () => auth()->user()?->can('users.manage') && $this->record->user?->is_active)
                ->action(function () {
                    $this->record->user->update(['is_active' => false]);

                    app(AuditLogService::class)->log('deactivated', $this->record->user, module: 'User');

                    Notification::make()->title('Login disabled')->success()->send();
                }),
            Actions\Action::make('activateLogin')
                ->label('Enable Login')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()?->can('users.manage') && $this->record->user && ! $this->record->user->is_active)
                ->action(function () {
                    $this->record->user->update(['is_active' => true]);

                    app(AuditLogService::class)->log('activated', $this->record->user, module: 'User');

                    Notification::make()->title('Login enabled')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
