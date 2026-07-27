<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeStatutoryDetail;
use App\Models\EmployeeType;
use App\Models\EmploymentType;
use App\Models\Grade;
use App\Models\ImportBatch;
use App\Models\Location;
use App\Models\SubDepartment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeImportService
{
    public const TEMPLATE_COLUMNS = [
        'employee_code', 'first_name', 'middle_name', 'last_name', 'official_email',
        'personal_mobile', 'dob', 'date_of_joining',
        'company_code', 'branch_code', 'location_name', 'department_code', 'sub_department_code',
        'designation_code', 'grade_code', 'employee_type_code', 'employment_type_code',
        'reporting_manager_employee_code',
        'pan', 'uan', 'pf_number', 'esic_number', 'bank_name', 'account_number', 'ifsc',
    ];

    private const REQUIRED = [
        'employee_code', 'first_name', 'official_email', 'personal_mobile',
        'date_of_joining', 'company_code', 'department_code', 'designation_code',
        'employee_type_code', 'employment_type_code',
    ];

    public function validateBatch(ImportBatch $batch): void
    {
        $rows = $batch->rows()->orderBy('row_number')->get();
        $seenCodes = [];
        $seenEmails = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $errors = $this->validateRow($row->raw_data, $seenCodes, $seenEmails);

            if ($errors === []) {
                $row->update(['status' => 'valid', 'errors' => null]);
                $successCount++;
            } else {
                $row->update(['status' => 'invalid', 'errors' => $errors]);
                $failedCount++;
            }
        }

        $batch->update([
            'total_rows' => $rows->count(),
            'success_rows' => $successCount,
            'failed_rows' => $failedCount,
            'status' => 'previewed',
        ]);
    }

    private function validateRow(array $data, array &$seenCodes, array &$seenEmails): array
    {
        $errors = [];
        $code = trim((string) ($data['employee_code'] ?? ''));
        $email = trim((string) ($data['official_email'] ?? ''));

        foreach (self::REQUIRED as $field) {
            if (blank($data[$field] ?? null)) {
                $errors[] = "{$field} is required";
            }
        }

        if ($code !== '') {
            if (in_array($code, $seenCodes, true)) {
                $errors[] = 'Duplicate employee_code within file';
            }
            $seenCodes[] = $code;

            if (Employee::withTrashed()->where('employee_code', $code)->exists()) {
                $errors[] = 'employee_code already exists';
            }
        }

        if ($email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'official_email is invalid';
            } elseif (in_array($email, $seenEmails, true)) {
                $errors[] = 'Duplicate official_email within file';
            } elseif (Employee::withTrashed()->where('official_email', $email)->exists()) {
                $errors[] = 'official_email already exists';
            }
            $seenEmails[] = $email;
        }

        foreach (['dob', 'date_of_joining'] as $dateField) {
            $value = $data[$dateField] ?? null;
            if (filled($value) && $this->parseDate($value) === null) {
                $errors[] = "{$dateField} is not a valid date";
            }
        }

        $company = $this->lookup(Company::class, 'code', $data['company_code'] ?? null);
        if (filled($data['company_code'] ?? null) && ! $company) {
            $errors[] = 'company_code not found';
        }

        $branch = null;
        if (filled($data['branch_code'] ?? null)) {
            $branch = Branch::query()->where('code', $data['branch_code'])
                ->when($company, fn ($q) => $q->where('company_id', $company->id))
                ->first();
            if (! $branch) {
                $errors[] = 'branch_code not found for company';
            }
        }

        if (filled($data['location_name'] ?? null) && $branch) {
            $exists = Location::query()->where('branch_id', $branch->id)
                ->where('name', $data['location_name'])->exists();
            if (! $exists) {
                $errors[] = 'location_name not found for branch';
            }
        }

        $department = null;
        if (filled($data['department_code'] ?? null)) {
            $department = Department::query()->where('code', $data['department_code'])
                ->when($company, fn ($q) => $q->where('company_id', $company->id))
                ->first();
            if (! $department) {
                $errors[] = 'department_code not found';
            }
        }

        if (filled($data['sub_department_code'] ?? null) && $department) {
            $exists = SubDepartment::query()->where('department_id', $department->id)
                ->where('code', $data['sub_department_code'])->exists();
            if (! $exists) {
                $errors[] = 'sub_department_code not found for department';
            }
        }

        foreach ([
            'designation_code' => Designation::class,
            'grade_code' => Grade::class,
            'employee_type_code' => EmployeeType::class,
            'employment_type_code' => EmploymentType::class,
        ] as $field => $modelClass) {
            if (filled($data[$field] ?? null) && ! $this->lookup($modelClass, 'code', $data[$field])) {
                $errors[] = "{$field} not found";
            }
        }

        if (filled($data['reporting_manager_employee_code'] ?? null)) {
            $exists = Employee::query()->where('employee_code', $data['reporting_manager_employee_code'])->exists();
            if (! $exists) {
                $errors[] = 'reporting_manager_employee_code not found';
            }
        }

        if (filled($data['pan'] ?? null) && ! preg_match('/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/', (string) $data['pan'])) {
            $errors[] = 'pan format is invalid';
        }

        $hasAccountNumber = filled($data['account_number'] ?? null);
        $hasIfsc = filled($data['ifsc'] ?? null);
        if ($hasAccountNumber xor $hasIfsc) {
            $errors[] = 'account_number and ifsc must be provided together';
        }

        return $errors;
    }

    public function importBatch(ImportBatch $batch, User $user): void
    {
        DB::transaction(function () use ($batch, $user) {
            $rows = $batch->rows()->where('status', 'valid')->orderBy('row_number')->get();
            $imported = 0;

            foreach ($rows as $row) {
                $data = $row->raw_data;

                $company = $this->lookup(Company::class, 'code', $data['company_code'] ?? null);
                $branch = filled($data['branch_code'] ?? null)
                    ? Branch::query()->where('code', $data['branch_code'])->when($company, fn ($q) => $q->where('company_id', $company->id))->first()
                    : null;
                $location = filled($data['location_name'] ?? null) && $branch
                    ? Location::query()->where('branch_id', $branch->id)->where('name', $data['location_name'])->first()
                    : null;
                $department = $this->lookup(Department::class, 'code', $data['department_code'] ?? null, $company ? ['company_id' => $company->id] : []);
                $subDepartment = filled($data['sub_department_code'] ?? null) && $department
                    ? SubDepartment::query()->where('department_id', $department->id)->where('code', $data['sub_department_code'])->first()
                    : null;
                $designation = $this->lookup(Designation::class, 'code', $data['designation_code'] ?? null);
                $grade = $this->lookup(Grade::class, 'code', $data['grade_code'] ?? null);
                $employeeType = $this->lookup(EmployeeType::class, 'code', $data['employee_type_code'] ?? null);
                $employmentType = $this->lookup(EmploymentType::class, 'code', $data['employment_type_code'] ?? null);
                $manager = filled($data['reporting_manager_employee_code'] ?? null)
                    ? Employee::query()->where('employee_code', $data['reporting_manager_employee_code'])->first()
                    : null;

                $employee = Employee::create([
                    'employee_code' => $data['employee_code'],
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'official_email' => $data['official_email'],
                    'personal_mobile' => $data['personal_mobile'],
                    'dob' => $this->parseDate($data['dob'] ?? null),
                    'date_of_joining' => $this->parseDate($data['date_of_joining'] ?? null),
                    'company_id' => $company?->id,
                    'branch_id' => $branch?->id,
                    'location_id' => $location?->id,
                    'department_id' => $department?->id,
                    'sub_department_id' => $subDepartment?->id,
                    'designation_id' => $designation?->id,
                    'grade_id' => $grade?->id,
                    'employee_type_id' => $employeeType?->id,
                    'employment_type_id' => $employmentType?->id,
                    'reporting_manager_id' => $manager?->id,
                    'status' => Employee::STATUS_ACTIVE,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                if (filled($data['pan'] ?? null) || filled($data['uan'] ?? null) || filled($data['pf_number'] ?? null) || filled($data['esic_number'] ?? null)) {
                    EmployeeStatutoryDetail::create([
                        'employee_id' => $employee->id,
                        'pan' => $data['pan'] ?? null,
                        'uan' => $data['uan'] ?? null,
                        'pf_number' => $data['pf_number'] ?? null,
                        'esic_number' => $data['esic_number'] ?? null,
                    ]);
                }

                if (filled($data['account_number'] ?? null)) {
                    EmployeeBankDetail::create([
                        'employee_id' => $employee->id,
                        'bank_name' => $data['bank_name'] ?? null,
                        'account_number' => $data['account_number'],
                        'ifsc' => $data['ifsc'] ?? null,
                        'is_primary' => true,
                    ]);
                }

                $row->update(['status' => 'imported', 'imported_id' => $employee->id]);
                $imported++;
            }

            $batch->update([
                'status' => 'imported',
                'success_rows' => $imported,
            ]);
        });
    }

    private function lookup(string $modelClass, string $column, ?string $value, array $extraWhere = []): ?object
    {
        if (blank($value)) {
            return null;
        }

        return $modelClass::query()->where($column, $value)->where($extraWhere)->first();
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
