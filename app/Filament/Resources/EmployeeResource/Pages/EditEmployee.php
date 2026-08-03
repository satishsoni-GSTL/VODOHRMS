<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\User;
use App\Notifications\PasswordResetByAdminNotification;
use App\Notifications\WelcomeAccountNotification;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

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

                    $user->notify(new WelcomeAccountNotification($user, $tempPassword));

                    Notification::make()
                        ->title('Login created')
                        ->body("Welcome email with temporary credentials sent to {$email}.")
                        ->success()
                        ->send();
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

                    $user->notify(new PasswordResetByAdminNotification($user, $plainPassword));

                    app(AuditLogService::class)->log(
                        'password_reset',
                        $user,
                        newValues: ['must_change_password' => $data['must_change_password']],
                        module: 'User',
                    );

                    Notification::make()
                        ->title('Password reset')
                        ->body("New password emailed to {$user->email}.")
                        ->success()
                        ->send();
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
