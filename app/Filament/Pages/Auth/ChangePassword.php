<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The panel's profile page (registered via ->profile() in AdminPanelProvider), reached
 * from the user-menu dropdown. Unlike the base EditProfile, this drops name/email editing
 * entirely (those live on Employee, managed via EmployeeResource) and keeps just a
 * password change — with a current-password check the base class doesn't have.
 */
class ChangePassword extends BaseEditProfile
{
    public function getTitle(): string|Htmlable
    {
        return 'Change Password';
    }

    public static function getLabel(): string
    {
        return 'Change Password';
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('current_password')
            ->label('Current Password')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->dehydrated(false)
            ->rule('current_password:' . Filament::getAuthGuard())
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->required();
    }

    /**
     * @return array<int|string, string|Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getCurrentPasswordFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['must_change_password'] = false;

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return Filament::getUrl();
    }
}
