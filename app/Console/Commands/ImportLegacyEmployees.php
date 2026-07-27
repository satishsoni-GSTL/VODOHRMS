<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeSalaryStructure;
use App\Models\EmployeeSalaryStructureLine;
use App\Models\EmployeeType;
use App\Models\EmploymentType;
use App\Models\SalaryComponent;
use App\Models\SubDepartment;
use App\Models\User;
use App\Notifications\WelcomeAccountNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportLegacyEmployees extends Command
{
    protected $signature = 'legacy:import-employees
        {--commit : Actually persist the import. Without this flag the command runs as a dry run and rolls back.}
        {--notify : When combined with --commit, email each newly created login its temporary password.}';

    protected $description = 'Import employees from the legacy GlobalSpace HRMS staging database into VODOHRMS.';

    /** @var array<int, array{code:string,email:string}> */
    private array $issuedCredentials = [];

    /** @var array<int, string> */
    private array $anomalies = [];

    private bool $notifyNewLogins = false;

    public function handle(): int
    {
        config([
            'database.connections.legacy_staging' => array_merge(
                config('database.connections.mysql'),
                ['database' => 'legacy_staging'],
            ),
        ]);

        $legacy = DB::connection('legacy_staging');
        $commit = (bool) $this->option('commit');
        $this->notifyNewLogins = $commit && (bool) $this->option('notify');

        $this->info($commit ? 'Running LIVE import (will commit to vodohrms database).' : 'Running DRY RUN (no changes will be committed).');

        DB::beginTransaction();

        try {
            $stats = $this->import($legacy);

            if (! $commit) {
                DB::rollBack();
                $this->warn('Dry run complete — transaction rolled back, nothing was written.');
            } else {
                DB::commit();
                $this->info('Import committed.');
            }

            $this->printReport($stats, $commit);

            return self::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Import failed and was rolled back: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function import(\Illuminate\Database\Connection $legacy): array
    {
        $companyId = Company::query()->value('id');

        $departmentMap = $this->importDepartments($legacy, $companyId);
        $designationMap = $this->importDesignations($legacy);
        $employmentTypeMap = $this->importEmploymentTypes($legacy);
        $employeeTypeMap = $this->importEmployeeTypes($legacy);

        $genders = $legacy->table('gender')->pluck('gender_name', 'id');
        $maritals = $legacy->table('marital_info')->pluck('marital_sta', 'id');

        $employeeRows = $legacy->table('employee_history')->orderBy('employee_id')->get();
        $bankRows = $legacy->table('gmb_bank_info')->get()->groupBy('employee_id');
        $salaryRows = $legacy->table('gmb_employee_file')->get()->groupBy('employee_id');
        $legacyUserEmails = $legacy->table('user')->pluck('email')->map(fn ($e) => strtolower(trim($e)))->unique()->flip();

        $salaryComponents = SalaryComponent::query()->pluck('id', 'code');

        $legacyIdToEmployeeId = [];
        $superVisorByEmployee = [];
        $created = 0;
        $bankCreated = 0;
        $salaryCreated = 0;
        $usersCreated = 0;

        foreach ($employeeRows as $row) {
            $employeeCode = 'LEG'.str_pad((string) $row->employee_id, 3, '0', STR_PAD_LEFT);

            $gender = $this->mapGender($genders[$row->gender] ?? null);
            $marital = $this->mapMarital($maritals[$row->marital_status] ?? null);

            $deptInfo = $departmentMap[$row->dept_id] ?? ['department_id' => null, 'sub_department_id' => null];

            $email = trim((string) $row->email);

            $employee = Employee::create([
                'employee_code' => $employeeCode,
                'first_name' => $row->first_name,
                'middle_name' => $row->middle_name ?: null,
                'last_name' => $row->last_name ?: null,
                'display_name' => trim(preg_replace('/\s+/', ' ', "{$row->first_name} {$row->middle_name} {$row->last_name}")),
                'dob' => $this->nullableDate($row->dob),
                'gender' => $gender,
                'marital_status' => $marital,
                'personal_mobile' => $row->phone ?: null,
                'alternate_mobile' => $row->alter_phone ?: null,
                'official_email' => $email !== '' ? $email : null,
                'current_address' => $row->present_address ? ['line1' => $row->present_address] : null,
                'permanent_address' => $row->parmanent_address ? ['line1' => $row->parmanent_address] : null,
                'city' => $row->city ?: null,
                'state' => $row->state ?: null,
                'country' => 'India',
                'pincode' => $row->zip ? (string) $row->zip : null,
                'company_id' => $companyId,
                'department_id' => $deptInfo['department_id'],
                'sub_department_id' => $deptInfo['sub_department_id'],
                'designation_id' => $designationMap[$row->pos_id] ?? null,
                'employee_type_id' => $employeeTypeMap[$row->employee_type] ?? null,
                'employment_type_id' => $employmentTypeMap[$row->duty_type] ?? null,
                'date_of_joining' => $this->nullableDate($row->hire_date),
                'status' => Employee::STATUS_ACTIVE,
            ]);

            $created++;
            $legacyIdToEmployeeId[$row->employee_id] = $employee->id;

            $superVisorId = trim((string) $row->super_visor_id);
            if ($superVisorId !== '' && $superVisorId !== '0' && ctype_digit($superVisorId) && (int) $superVisorId !== $row->employee_id) {
                $superVisorByEmployee[$employee->id] = (int) $superVisorId;
            } elseif ($superVisorId !== '' && $superVisorId !== '0' && ! ctype_digit($superVisorId)) {
                $this->anomalies[] = "Employee {$employeeCode} (legacy id {$row->employee_id}): unresolvable super_visor_id '{$superVisorId}', left without a manager.";
            }

            $bank = ($bankRows[$row->employee_id] ?? collect())->first();
            if ($bank && trim((string) $bank->acc_number) !== '') {
                EmployeeBankDetail::create([
                    'employee_id' => $employee->id,
                    'account_holder_name' => trim("{$row->first_name} {$row->last_name}"),
                    'bank_name' => $bank->bank_name ?: null,
                    'account_number' => $bank->acc_number,
                    'branch_name' => $bank->branch_address ?: null,
                    'is_primary' => true,
                ]);
                $bankCreated++;
            }

            $salary = ($salaryRows[$row->employee_id] ?? collect())->first();
            if ($salary) {
                $grossSalary = $this->parseAmount($salary->gross_salary);
                if ($grossSalary !== null && $grossSalary > 0) {
                    $structure = EmployeeSalaryStructure::create([
                        'employee_id' => $employee->id,
                        'effective_from' => $this->nullableDate($row->hire_date) ?? now()->toDateString(),
                        'annual_ctc' => round($grossSalary * 12, 2),
                        'monthly_gross' => $grossSalary,
                        'remarks' => 'Migrated from legacy GlobalSpace HRMS',
                        'is_active' => true,
                    ]);

                    $lines = [
                        'BASIC' => $this->parseAmount($salary->basic),
                        'HRA' => $this->parseAmount($salary->house_rent_allowance),
                        'CONVEYANCE' => $this->parseAmount($salary->conveyance_allowance),
                        'SPECIAL_ALLOWANCE' => ($this->parseAmount($salary->medical_allowance) ?? 0) + ($this->parseAmount($salary->other_allowance) ?? 0),
                        'PROFESSIONAL_TAX' => $this->parseAmount($salary->professional_tax),
                        'PF_EMPLOYEE' => $this->parseAmount($salary->provident_fund),
                    ];

                    foreach ($lines as $code => $amount) {
                        if ($amount === null || $amount <= 0 || ! isset($salaryComponents[$code])) {
                            continue;
                        }

                        EmployeeSalaryStructureLine::create([
                            'structure_id' => $structure->id,
                            'salary_component_id' => $salaryComponents[$code],
                            'monthly_amount' => $amount,
                            'annual_amount' => round($amount * 12, 2),
                        ]);
                    }

                    $salaryCreated++;
                }
            }

            if ($email !== '' && isset($legacyUserEmails[strtolower($email)])) {
                $tempPassword = Str::password(14);
                $user = User::create([
                    'employee_id' => $employee->id,
                    'employee_code' => $employeeCode,
                    'name' => trim("{$row->first_name} {$row->last_name}"),
                    'email' => $email,
                    'password' => $tempPassword,
                    'must_change_password' => true,
                    'is_active' => true,
                ]);
                $this->issuedCredentials[] = ['code' => $employeeCode, 'email' => $email, 'password' => $tempPassword];
                $usersCreated++;

                if ($this->notifyNewLogins) {
                    $user->notify(new WelcomeAccountNotification($user, $tempPassword));
                }
            }
        }

        $managersLinked = 0;
        foreach ($superVisorByEmployee as $employeeId => $legacySupervisorId) {
            if (isset($legacyIdToEmployeeId[$legacySupervisorId])) {
                Employee::whereKey($employeeId)->update(['reporting_manager_id' => $legacyIdToEmployeeId[$legacySupervisorId]]);
                $managersLinked++;
            }
        }

        $sample = Employee::query()
            ->whereIn('id', array_slice($legacyIdToEmployeeId, 0, 5))
            ->get(['employee_code', 'first_name', 'last_name', 'department_id', 'designation_id', 'status'])
            ->map(fn ($e) => [$e->employee_code, $e->first_name, $e->last_name, $e->department_id, $e->designation_id, $e->status])
            ->all();

        return [
            'employees' => $created,
            'bank_details' => $bankCreated,
            'salary_structures' => $salaryCreated,
            'users' => $usersCreated,
            'managers_linked' => $managersLinked,
            'sample' => $sample,
        ];
    }

    private function importDepartments(\Illuminate\Database\Connection $legacy, ?int $companyId): array
    {
        $rows = $legacy->table('department')->get()->keyBy('dept_id');
        $map = [];

        foreach ($rows as $row) {
            if ((int) $row->parent_id === 0) {
                $dept = Department::firstOrCreate(
                    ['company_id' => $companyId, 'code' => $this->slugCode('DEPT', $row->dept_id, $row->department_name)],
                    ['name' => trim($row->department_name)],
                );
                $map[$row->dept_id] = ['department_id' => $dept->id, 'sub_department_id' => null];
            }
        }

        foreach ($rows as $row) {
            if ((int) $row->parent_id !== 0) {
                $parent = $map[$row->parent_id] ?? null;
                if (! $parent) {
                    continue;
                }
                $sub = SubDepartment::firstOrCreate(
                    ['department_id' => $parent['department_id'], 'code' => $this->slugCode('SUBDEPT', $row->dept_id, $row->department_name)],
                    ['name' => trim($row->department_name)],
                );
                $map[$row->dept_id] = ['department_id' => $parent['department_id'], 'sub_department_id' => $sub->id];
            }
        }

        return $map;
    }

    private function importDesignations(\Illuminate\Database\Connection $legacy): array
    {
        $map = [];
        foreach ($legacy->table('position')->get() as $row) {
            $designation = Designation::firstOrCreate(
                ['code' => $this->slugCode('POS', $row->pos_id, $row->position_name)],
                ['name' => trim($row->position_name)],
            );
            $map[$row->pos_id] = $designation->id;
        }

        return $map;
    }

    private function importEmploymentTypes(\Illuminate\Database\Connection $legacy): array
    {
        $map = [];
        foreach ($legacy->table('duty_type')->get() as $row) {
            if ((int) $row->id === 0) {
                continue;
            }
            $type = EmploymentType::firstOrCreate(
                ['code' => $this->slugCode('LEGDUTY', $row->id, $row->type_name)],
                ['name' => trim($row->type_name)],
            );
            $map[$row->id] = $type->id;
        }

        return $map;
    }

    private function importEmployeeTypes(\Illuminate\Database\Connection $legacy): array
    {
        $map = [];
        foreach ($legacy->table('gmb_employee_types')->get() as $row) {
            $type = EmployeeType::firstOrCreate(
                ['code' => $this->slugCode('LEGTYPE', $row->id, $row->name)],
                ['name' => trim($row->name)],
            );
            $map[$row->id] = $type->id;
        }

        return $map;
    }

    private function slugCode(string $prefix, int|string $id, string $name): string
    {
        return Str::upper($prefix.'-'.$id.'-'.Str::slug($name, '_'));
    }

    private function mapGender(?string $name): ?string
    {
        return match (strtolower((string) $name)) {
            'male' => 'male',
            'female' => 'female',
            'other' => 'other',
            default => null,
        };
    }

    private function mapMarital(?string $name): ?string
    {
        return match (strtolower((string) $name)) {
            'single' => 'single',
            'married' => 'married',
            'divorced', 'widowed', 'other' => 'other',
            default => null,
        };
    }

    private function nullableDate(?string $date): ?string
    {
        if (! $date || $date === '0000-00-00') {
            return null;
        }

        return $date;
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace(',', '', (string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function printReport(array $stats, bool $committed): void
    {
        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Employees', $stats['employees']],
            ['Bank details', $stats['bank_details']],
            ['Salary structures', $stats['salary_structures']],
            ['User logins created', $stats['users']],
            ['Reporting managers linked', $stats['managers_linked']],
        ]);

        if ($this->anomalies !== []) {
            $this->newLine();
            $this->warn('Anomalies:');
            foreach ($this->anomalies as $note) {
                $this->line(" - {$note}");
            }
        }

        if ($committed && $this->issuedCredentials !== []) {
            $path = storage_path('app/legacy_import_credentials_'.date('Ymd_His').'.csv');
            $fh = fopen($path, 'w');
            fputcsv($fh, ['employee_code', 'email', 'temporary_password']);
            foreach ($this->issuedCredentials as $cred) {
                fputcsv($fh, [$cred['code'], $cred['email'], $cred['password']]);
            }
            fclose($fh);
            $this->newLine();
            $this->warn("Temporary passwords for {$stats['users']} migrated logins written to: {$path}");
            $this->warn('Distribute these securely and delete the file afterward. All accounts require a password change on first login.');
        } elseif (! $committed && ($stats['sample'] ?? [])) {
            $this->newLine();
            $this->comment('Sample of employees that would be created:');
            $this->table(['Code', 'First', 'Last', 'Dept ID', 'Designation ID', 'Status'], $stats['sample']);
        }
    }
}
