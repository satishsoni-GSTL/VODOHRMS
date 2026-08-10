<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use Auditable, SoftDeletes;

    protected function auditModule(): string
    {
        return 'employee';
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PROBATION = 'probation';

    public const STATUS_NOTICE_PERIOD = 'notice_period';

    public const STATUS_RESIGNED = 'resigned';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_EXITED = 'exited';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PROBATION => 'Probation',
        self::STATUS_NOTICE_PERIOD => 'Notice Period',
        self::STATUS_RESIGNED => 'Resigned',
        self::STATUS_TERMINATED => 'Terminated',
        self::STATUS_EXITED => 'Exited',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    protected $fillable = [
        'employee_code', 'biometric_enroll_id', 'first_name', 'middle_name', 'last_name', 'display_name',
        'dob', 'gender', 'marital_status', 'blood_group',
        'personal_mobile', 'alternate_mobile', 'personal_email', 'official_email', 'profile_photo_path',
        'current_address', 'permanent_address', 'city', 'state', 'country', 'pincode',
        'company_id', 'branch_id', 'location_id', 'department_id', 'sub_department_id',
        'designation_id', 'grade_id', 'cost_center_id', 'employee_type_id', 'employment_type_id',
        'reporting_manager_id', 'hr_manager_id',
        'date_of_joining', 'confirmation_date', 'probation_period_days', 'notice_period_days',
        'weekly_off', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'date_of_joining' => 'date:Y-m-d',
            'confirmation_date' => 'date:Y-m-d',
            'current_address' => 'array',
            'permanent_address' => 'array',
            'weekly_off' => 'array',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subDepartment(): BelongsTo
    {
        return $this->belongsTo(SubDepartment::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function hrManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hr_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function statutoryDetail(): HasOne
    {
        return $this->hasOne(EmployeeStatutoryDetail::class);
    }

    public function bankDetails(): HasMany
    {
        return $this->hasMany(EmployeeBankDetail::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceRegularizations(): HasMany
    {
        return $this->hasMany(AttendanceRegularization::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructure::class);
    }

    public function payrollInputs(): HasMany
    {
        return $this->hasMany(PayrollInput::class);
    }

    public function currentSalaryStructure(): ?EmployeeSalaryStructure
    {
        return $this->salaryStructures()
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
            ->orderByDesc('effective_from')
            ->first();
    }

    public function taxRegimes(): HasMany
    {
        return $this->hasMany(EmployeeTaxRegime::class);
    }

    public function taxDeclarations(): HasMany
    {
        return $this->hasMany(EmployeeTaxDeclaration::class);
    }

    public function taxProjections(): HasMany
    {
        return $this->hasMany(EmployeeTaxProjection::class);
    }

    public function selectedRegimeFor(FinancialYear $financialYear): ?string
    {
        return $this->taxRegimes()
            ->where('financial_year_id', $financialYear->id)
            // Tie-break by id when two selections land on the same date (e.g. HR fixes a
            // regime the same day the employee first picked one) — selection_date alone
            // doesn't disambiguate which row is actually the latest.
            ->orderByDesc('selection_date')
            ->orderByDesc('id')
            ->value('selected_regime');
    }

    public function onboardingChecklist(): HasOne
    {
        return $this->hasOne(OnboardingChecklist::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function resignations(): HasMany
    {
        return $this->hasMany(Resignation::class);
    }

    /**
     * All employees reporting to this one, directly or indirectly.
     *
     * @return array<int>
     */
    public function allSubordinateIds(): array
    {
        $ids = [];
        $queue = [$this->id];

        while ($queue !== []) {
            $managerId = array_shift($queue);
            $reportIds = static::query()
                ->where('reporting_manager_id', $managerId)
                ->pluck('id')
                ->all();

            foreach ($reportIds as $reportId) {
                if (! in_array($reportId, $ids, true)) {
                    $ids[] = $reportId;
                    $queue[] = $reportId;
                }
            }
        }

        return $ids;
    }
}
