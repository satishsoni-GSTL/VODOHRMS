<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Rules\NotCircularReportingManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Employees';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $canViewSensitive = auth()->user()?->can('employee.view-sensitive') ?? false;

        return $form->schema([
            Forms\Components\Tabs::make('Employee')
                ->columnSpanFull()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Basic Details')
                        ->schema([
                            Forms\Components\FileUpload::make('profile_photo_path')
                                ->image()
                                ->avatar()
                                ->directory('employee-photos')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('employee_code')
                                ->required()
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),
                            Forms\Components\Select::make('status')
                                ->options(Employee::STATUSES)
                                ->default(Employee::STATUS_ACTIVE)
                                ->required(),
                            Forms\Components\TextInput::make('first_name')->required()->maxLength(100),
                            Forms\Components\TextInput::make('middle_name')->maxLength(100),
                            Forms\Components\TextInput::make('last_name')->maxLength(100),
                            Forms\Components\TextInput::make('display_name')->maxLength(150),
                            Forms\Components\DatePicker::make('dob')->label('Date of Birth'),
                            Forms\Components\Select::make('gender')
                                ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other']),
                            Forms\Components\Select::make('marital_status')
                                ->options(['single' => 'Single', 'married' => 'Married', 'other' => 'Other']),
                            Forms\Components\TextInput::make('blood_group')->maxLength(5),
                            Forms\Components\TextInput::make('personal_mobile')->tel()->maxLength(20),
                            Forms\Components\TextInput::make('alternate_mobile')->tel()->maxLength(20),
                            Forms\Components\TextInput::make('personal_email')->email()->maxLength(150),
                            Forms\Components\TextInput::make('official_email')
                                ->email()
                                ->maxLength(150)
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->withoutTrashed(),
                                )
                                ->rule(fn (?Employee $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                    $trashed = Employee::onlyTrashed()
                                        ->where('official_email', $value)
                                        ->when($record, fn (Builder $q) => $q->where('id', '!=', $record->id))
                                        ->first();

                                    if ($trashed) {
                                        $fail("This email belongs to a deleted employee record ({$trashed->employee_code} - {$trashed->full_name}). Restore that record instead — filter Employees by \"Trashed\" and use Restore.");
                                    }
                                }),
                        ])
                        ->columns(2),

                    Forms\Components\Tabs\Tab::make('Address')
                        ->schema([
                            Forms\Components\Fieldset::make('Current Address')
                                ->schema([
                                    Forms\Components\TextInput::make('current_address.line1')->label('Address Line 1'),
                                    Forms\Components\TextInput::make('current_address.line2')->label('Address Line 2'),
                                    Forms\Components\TextInput::make('current_address.city')->label('City'),
                                    Forms\Components\TextInput::make('current_address.state')->label('State'),
                                    Forms\Components\TextInput::make('current_address.pincode')->label('PIN Code'),
                                ])
                                ->columns(2),
                            Forms\Components\Fieldset::make('Permanent Address')
                                ->schema([
                                    Forms\Components\TextInput::make('permanent_address.line1')->label('Address Line 1'),
                                    Forms\Components\TextInput::make('permanent_address.line2')->label('Address Line 2'),
                                    Forms\Components\TextInput::make('permanent_address.city')->label('City'),
                                    Forms\Components\TextInput::make('permanent_address.state')->label('State'),
                                    Forms\Components\TextInput::make('permanent_address.pincode')->label('PIN Code'),
                                ])
                                ->columns(2),
                            Forms\Components\TextInput::make('city')->label('Mailing City'),
                            Forms\Components\TextInput::make('state')->label('Mailing State'),
                            Forms\Components\TextInput::make('country')->default('India'),
                            Forms\Components\TextInput::make('pincode')->label('Mailing PIN Code'),
                        ])
                        ->columns(2),

                    Forms\Components\Tabs\Tab::make('Employment')
                        ->schema([
                            Forms\Components\Select::make('company_id')
                                ->relationship('company', 'name')->searchable()->preload()->live(),
                            Forms\Components\Select::make('branch_id')
                                ->relationship('branch', 'name', fn (Builder $query, Forms\Get $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query)
                                ->searchable()->preload(),
                            Forms\Components\Select::make('location_id')
                                ->relationship('location', 'name')->searchable()->preload(),
                            Forms\Components\Select::make('department_id')
                                ->relationship('department', 'name')->searchable()->preload()->live(),
                            Forms\Components\Select::make('sub_department_id')
                                ->relationship('subDepartment', 'name', fn (Builder $query, Forms\Get $get) => $get('department_id') ? $query->where('department_id', $get('department_id')) : $query)
                                ->searchable()->preload(),
                            Forms\Components\Select::make('designation_id')
                                ->relationship('designation', 'name')->searchable()->preload(),
                            Forms\Components\Select::make('grade_id')
                                ->relationship('grade', 'name')->searchable()->preload(),
                            Forms\Components\Select::make('cost_center_id')
                                ->relationship('costCenter', 'name')->searchable()->preload(),
                            Forms\Components\Select::make('employee_type_id')
                                ->relationship('employeeType', 'name')->searchable()->preload(),
                            Forms\Components\Select::make('employment_type_id')
                                ->relationship('employmentType', 'name')->searchable()->preload(),
                            Forms\Components\Select::make('reporting_manager_id')
                                ->label('Reporting Manager')
                                ->relationship('reportingManager', 'employee_code')
                                ->getOptionLabelFromRecordUsing(fn (Employee $r) => "{$r->employee_code} - {$r->full_name}")
                                ->searchable(['employee_code', 'first_name', 'last_name'])
                                ->preload()
                                ->rules(fn (?Employee $record) => [new NotCircularReportingManager($record?->id)]),
                            Forms\Components\Select::make('hr_manager_id')
                                ->label('HR Manager')
                                ->relationship('hrManager', 'employee_code')
                                ->getOptionLabelFromRecordUsing(fn (Employee $r) => "{$r->employee_code} - {$r->full_name}")
                                ->searchable(['employee_code', 'first_name', 'last_name'])
                                ->preload(),
                            Forms\Components\DatePicker::make('date_of_joining')->required(),
                            Forms\Components\DatePicker::make('confirmation_date'),
                            Forms\Components\TextInput::make('probation_period_days')->numeric(),
                            Forms\Components\TextInput::make('notice_period_days')->numeric(),
                            Forms\Components\TextInput::make('biometric_enroll_id')
                                ->label('Biometric Device ID')
                                ->helperText('The user/enrollment ID configured for this employee on the biometric device.')
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),
                        ])
                        ->columns(2),

                    Forms\Components\Tabs\Tab::make('Statutory & Bank')
                        ->visible($canViewSensitive)
                        ->schema([
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('pan')->label('PAN')->maxLength(20),
                                Forms\Components\TextInput::make('aadhaar')->label('Aadhaar')->maxLength(20),
                                Forms\Components\TextInput::make('uan')->label('UAN')->maxLength(20),
                                Forms\Components\TextInput::make('pf_number')->label('PF Number')->maxLength(50),
                                Forms\Components\TextInput::make('esic_number')->label('ESIC Number')->maxLength(50),
                                Forms\Components\Toggle::make('professional_tax_applicable')->default(true),
                            ])
                                ->relationship('statutoryDetail')
                                ->columns(2),

                            Forms\Components\Repeater::make('bankDetails')
                                ->relationship()
                                ->schema([
                                    Forms\Components\TextInput::make('account_holder_name')->maxLength(150),
                                    Forms\Components\TextInput::make('bank_name')->maxLength(150),
                                    Forms\Components\TextInput::make('account_number')->maxLength(30),
                                    Forms\Components\TextInput::make('ifsc')->label('IFSC')->maxLength(20),
                                    Forms\Components\TextInput::make('branch_name')->maxLength(150),
                                    Forms\Components\Toggle::make('is_primary')->default(true),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->collapsible(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo_path')->circular()->label(''),
                Tables\Columns\TextColumn::make('employee_code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('first_name', $direction)),
                Tables\Columns\TextColumn::make('official_email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('biometric_enroll_id')->label('Device ID')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('department.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('designation.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reportingManager.full_name')->label('Reporting Manager')->toggleable(),
                Tables\Columns\TextColumn::make('date_of_joining')->date()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Employee::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Employee::STATUS_ACTIVE => 'success',
                        Employee::STATUS_PROBATION => 'warning',
                        Employee::STATUS_NOTICE_PERIOD => 'warning',
                        Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED, Employee::STATUS_EXITED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Employee::STATUSES),
                Tables\Filters\SelectFilter::make('company_id')->relationship('company', 'name')->label('Company'),
                Tables\Filters\SelectFilter::make('department_id')->relationship('department', 'name')->label('Department'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn () => auth()->user()?->can('employee.delete') ?? false),
                Tables\Actions\RestoreAction::make()->visible(fn () => auth()->user()?->can('employee.delete') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ])->visible(fn () => auth()->user()?->can('employee.delete') ?? false),
            ])
            ->defaultSort('employee_code');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['department', 'designation', 'reportingManager']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'import' => Pages\ImportEmployees::route('/import'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
