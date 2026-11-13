<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeProfileService;
use App\Services\OnboardingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The logged-in employee's own profile: completion at a glance, a form to fill in what is
 * missing, and their documents. Contact / address / emergency details can always be
 * updated; identity, statutory IDs and bank details only filled in when empty (HR changes
 * them) — see EmployeeProfileService.
 *
 * @property Form $form
 */
class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'My Profile';

    protected static ?string $title = 'My Profile';

    protected static ?string $navigationGroup = 'Employees';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.my-profile';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->employee_id !== null;
    }

    public static function getNavigationBadge(): ?string
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            return null;
        }

        $percent = app(EmployeeProfileService::class)->completion($employee)['percent'];

        return $percent < 100 ? "{$percent}%" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    private function employee(): Employee
    {
        return auth()->user()->employee()->with(['statutoryDetail', 'bankDetails', 'documents'])->firstOrFail();
    }

    private function svc(): EmployeeProfileService
    {
        return app(EmployeeProfileService::class);
    }

    public function mount(): void
    {
        $this->form->fill($this->svc()->formData($this->employee()));
    }

    public function completion(): array
    {
        return $this->svc()->completion($this->employee());
    }

    /** @return \Illuminate\Support\Collection<int, EmployeeDocument> */
    public function documents()
    {
        return $this->employee()->documents->sortBy('document_type')->values();
    }

    public function form(Form $form): Form
    {
        $employee = $this->employee();
        $svc = $this->svc();
        $locked = fn (string $field) => ! $svc->isEditable($employee, $field, EmployeeProfileService::MODE_SELF);
        $hrHint = fn (string $field) => $locked($field) ? 'To change this, contact HR.' : null;

        $address = fn (string $field, string $label) => Forms\Components\Fieldset::make($label)
            ->schema([
                Forms\Components\TextInput::make("{$field}.line1")->label('Address line 1')->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make("{$field}.line2")->label('Address line 2')->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make("{$field}.city")->label('City')->maxLength(100),
                Forms\Components\TextInput::make("{$field}.state")->label('State')->maxLength(100),
                Forms\Components\TextInput::make("{$field}.pincode")->label('PIN code')->maxLength(6),
            ])
            ->columns(3);

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Personal details')
                    ->id('personal')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')->disabled($locked('first_name'))->helperText($hrHint('first_name')),
                        Forms\Components\TextInput::make('middle_name')->disabled($locked('middle_name')),
                        Forms\Components\TextInput::make('last_name')->disabled($locked('last_name'))->helperText($hrHint('last_name')),
                        Forms\Components\DatePicker::make('dob')->label('Date of birth')->disabled($locked('dob'))->helperText($hrHint('dob')),
                        Forms\Components\Select::make('gender')->options(EmployeeProfileService::GENDERS)->disabled($locked('gender')),
                        Forms\Components\Select::make('marital_status')->options(EmployeeProfileService::MARITAL),
                        Forms\Components\Select::make('blood_group')->options(array_combine(EmployeeProfileService::BLOOD_GROUPS, EmployeeProfileService::BLOOD_GROUPS)),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Forms\Components\Section::make('Contact')
                    ->id('contact')
                    ->schema([
                        Forms\Components\TextInput::make('personal_mobile')->tel()->maxLength(20),
                        Forms\Components\TextInput::make('alternate_mobile')->tel()->maxLength(20),
                        Forms\Components\TextInput::make('personal_email')->email()->maxLength(150),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Forms\Components\Section::make('Address')
                    ->id('address')
                    ->schema([
                        $address('current_address', 'Current address'),
                        $address('permanent_address', 'Permanent address'),
                    ])
                    ->collapsible(),
                Forms\Components\Section::make('Emergency contact')
                    ->id('emergency')
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact_name')->label('Name')->maxLength(150),
                        Forms\Components\TextInput::make('emergency_contact_relation')->label('Relationship')->maxLength(50),
                        Forms\Components\TextInput::make('emergency_contact_phone')->label('Phone')->tel()->maxLength(20),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Forms\Components\Section::make('Statutory IDs')
                    ->id('statutory')
                    ->schema([
                        Forms\Components\TextInput::make('pan')->label('PAN')->maxLength(10)->disabled($locked('pan'))->helperText($hrHint('pan')),
                        Forms\Components\TextInput::make('aadhaar')->label('Aadhaar')->maxLength(14)->disabled($locked('aadhaar'))->helperText($hrHint('aadhaar'))
                            ->formatStateUsing(fn ($state) => $locked('aadhaar') && $state ? 'XXXX XXXX '.substr(preg_replace('/\D/', '', (string) $state), -4) : $state),
                        Forms\Components\TextInput::make('uan')->label('UAN')->maxLength(12)->disabled($locked('uan'))->helperText($hrHint('uan')),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Forms\Components\Section::make('Salary bank account')
                    ->id('bank')
                    ->description($locked('bank') ? 'Your bank account is on file. To change it, contact HR.' : null)
                    ->schema([
                        Forms\Components\TextInput::make('bank.account_holder_name')->label('Account holder name')->disabled($locked('bank')),
                        Forms\Components\TextInput::make('bank.bank_name')->label('Bank name')->disabled($locked('bank')),
                        // Never echo a saved account number back to the browser.
                        Forms\Components\TextInput::make('bank.account_number')->label('Account number')
                            ->disabled($locked('bank'))
                            ->formatStateUsing(fn ($state) => $locked('bank') && $state ? '••••'.substr((string) $state, -4) : $state),
                        Forms\Components\TextInput::make('bank.ifsc')->label('IFSC')->maxLength(11)->disabled($locked('bank')),
                        Forms\Components\TextInput::make('bank.branch_name')->label('Branch')->disabled($locked('bank')),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public function save(): void
    {
        try {
            $this->svc()->update($this->employee(), $this->form->getState(), EmployeeProfileService::MODE_SELF);
        } catch (ValidationException $e) {
            // Show service validation errors against the form fields.
            $this->setErrorBag(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["data.{$k}" => $m])->all());
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        app(OnboardingService::class)->refresh($this->employee());
        Notification::make()->title('Profile saved')->success()->send();
        $this->redirect(static::getUrl());
    }

    public function uploadDocumentAction(): Action
    {
        return Action::make('uploadDocument')
            ->label('Upload document')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                Forms\Components\Select::make('document_type')->label('Document')->options(EmployeeDocument::types())->required(),
                Forms\Components\FileUpload::make('file')
                    ->disk('local')
                    ->directory('employee-documents/'.auth()->user()->employee_id)
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('file_name')
                    ->helperText('Photo (JPG, PNG, WEBP) or PDF, up to 10 MB.')
                    ->required(),
            ])
            ->action(function (array $data) {
                $employee = $this->employee();
                $this->svc()->recordDocument($employee, $data['document_type'], $data['file'], $data['file_name'] ?? null, 'self', auth()->user());
                app(OnboardingService::class)->refresh($employee);
                Notification::make()->title('Document uploaded')->success()->send();
            });
    }

    public function deleteDocument(int $id): void
    {
        $document = $this->employee()->documents()->whereKey($id)->firstOrFail();

        try {
            $this->svc()->deleteDocument($document);
            Notification::make()->title('Document removed')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
