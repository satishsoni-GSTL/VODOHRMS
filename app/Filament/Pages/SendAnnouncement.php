<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Employee;
use App\Notifications\AnnouncementNotification;
use App\Notifications\Concerns\NotifiesRecipients;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SendAnnouncement extends Page
{
    use NotifiesRecipients;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Notifications';

    protected static string $view = 'filament.pages.send-announcement';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('notification.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['recipient_scope' => 'all']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('subject')->required()->maxLength(255),
                Textarea::make('message')->required()->rows(6),
                Select::make('recipient_scope')
                    ->label('Send To')
                    ->options([
                        'all' => 'All Employees',
                        'company' => 'Specific Company',
                        'specific' => 'Specific Employees',
                    ])
                    ->default('all')
                    ->live()
                    ->required(),
                Select::make('company_id')
                    ->label('Company')
                    ->options(fn () => Company::query()->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('recipient_scope') === 'company')
                    ->required(fn (Get $get) => $get('recipient_scope') === 'company'),
                Select::make('employee_ids')
                    ->label('Employees')
                    ->multiple()
                    ->options(fn () => Employee::query()
                        ->where('status', Employee::STATUS_ACTIVE)
                        ->get()
                        ->mapWithKeys(fn (Employee $employee) => [$employee->id => "{$employee->full_name} ({$employee->employee_code})"]))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('recipient_scope') === 'specific')
                    ->required(fn (Get $get) => $get('recipient_scope') === 'specific'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Announcement')
                ->requiresConfirmation()
                ->action(fn () => $this->send()),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $employees = match ($data['recipient_scope']) {
            'company' => Employee::query()->where('status', Employee::STATUS_ACTIVE)->where('company_id', $data['company_id'])->get(),
            'specific' => Employee::query()->where('status', Employee::STATUS_ACTIVE)->whereIn('id', $data['employee_ids'] ?? [])->get(),
            default => Employee::query()->where('status', Employee::STATUS_ACTIVE)->get(),
        };

        $notification = new AnnouncementNotification($data['subject'], $data['message']);

        foreach ($employees as $employee) {
            $this->notifyEmployee($employee, $notification);
        }

        Notification::make()
            ->title('Announcement sent')
            ->body("Emailed {$employees->count()} employee(s).")
            ->success()
            ->send();

        $this->form->fill(['recipient_scope' => 'all']);
    }
}
