<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\User;
use App\Notifications\WelcomeAccountNotification;
use Filament\Actions;
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
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
