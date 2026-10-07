<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\EmployeeLeaveBalance;
use App\Models\ExpenseClaim;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use App\Services\CelebrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends MobileController
{
    /** Home screen: today, leave balances, my open requests, approvals waiting, next holidays. */
    public function dashboard(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $today = now()->toDateString();

        $attendance = Attendance::where('employee_id', $employee->id)->where('attendance_date', $today)->first();

        $balances = EmployeeLeaveBalance::query()
            ->with('leaveType:id,name,code')
            ->where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->filter(fn ($b) => $b->leaveType)
            ->map(fn ($b) => ['code' => $b->leaveType->code, 'name' => $b->leaveType->name, 'available' => (float) $b->closing_balance])
            ->values();

        $pending = [
            'leave' => LeaveApplication::where('employee_id', $employee->id)->where('status', LeaveApplication::STATUS_PENDING)->count(),
            'wfh' => WorkFromHomeRequest::where('employee_id', $employee->id)->where('status', WorkFromHomeRequest::STATUS_PENDING)->count(),
            'regularization' => AttendanceRegularization::where('employee_id', $employee->id)
                ->whereIn('status', [AttendanceRegularization::STATUS_PENDING, AttendanceRegularization::STATUS_MANAGER_APPROVED, AttendanceRegularization::STATUS_HR_APPROVED])->count(),
            'expense' => ExpenseClaim::where('employee_id', $employee->id)
                ->whereIn('status', [ExpenseClaim::STATUS_SUBMITTED, ExpenseClaim::STATUS_PENDING_FINANCE])->count(),
            'sent_back' => LeaveApplication::where('employee_id', $employee->id)->where('status', 'sent_back')->count()
                + WorkFromHomeRequest::where('employee_id', $employee->id)->where('status', 'sent_back')->count()
                + AttendanceRegularization::where('employee_id', $employee->id)->where('status', 'sent_back')->count()
                + ExpenseClaim::where('employee_id', $employee->id)->where('status', 'sent_back')->count(),
        ];

        $holidays = Holiday::query()
            ->where('date', '>=', $today)
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->orderBy('date')
            ->limit(3)
            ->get()
            ->map(fn (Holiday $h) => HolidayController::present($h))
            ->values();

        return response()->json([
            'user' => self::userSummary($this->user($request)),
            'today' => [
                'date' => $today,
                'status' => $attendance?->status,
                'status_label' => $attendance ? (Attendance::STATUSES[$attendance->status] ?? self::statusLabel($attendance->status)) : null,
                'first_in' => $attendance?->first_in,
                'last_out' => $attendance?->hasDistinctPunches() ? $attendance->last_out : null,
                'hours' => $attendance?->effective_hours !== null ? (float) $attendance->effective_hours : null,
                // Self clock-in/out is only for an approved Work From Home day.
                'can_clock' => $attendance?->status === Attendance::STATUS_WFH && ! $attendance->is_frozen,
            ],
            'leave_balances' => $balances,
            'my_pending' => $pending,
            'approvals_waiting' => ApprovalController::actionable($this->user($request))->count(),
            'upcoming_holidays' => $holidays,
            // Company-wide (all teams): today's and the coming week's birthdays & work anniversaries.
            'celebrations' => app(CelebrationService::class)->upcoming($employee, 7)->map(fn (array $c) => [
                'type' => $c['type'],
                'date' => $c['date'],
                'is_today' => $c['is_today'],
                'days_away' => $c['days_away'],
                'years' => $c['years'],
                'is_me' => $c['is_me'],
                'is_my_team' => $c['is_my_team'],
                'name' => self::cleanName($c['employee']->full_name),
                'employee_code' => $c['employee']->employee_code,
                'designation' => $c['employee']->designation?->name,
                'department' => $c['employee']->department?->name,
            ])->values(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $employee = $this->employee($request)->load([
            'company', 'branch', 'location', 'department', 'designation', 'grade', 'reportingManager', 'hrManager',
            'employmentType', 'bankDetails',
        ]);

        $bank = $employee->bankDetails->firstWhere('is_primary', true) ?? $employee->bankDetails->first();

        return response()->json([
            'user' => self::userSummary($this->user($request)),
            'personal' => [
                'date_of_birth' => $employee->dob?->toDateString(),
                'gender' => $employee->gender,
                'blood_group' => $employee->blood_group,
                'mobile' => $employee->personal_mobile,
                'personal_email' => $employee->personal_email,
                'address' => $employee->current_address,
            ],
            'job' => [
                'employee_code' => $employee->employee_code,
                'company' => $employee->company?->name,
                'branch' => $employee->branch?->name,
                'location' => $employee->location?->name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'grade' => $employee->grade?->name,
                'employment_type' => $employee->employmentType?->name,
                'date_of_joining' => $employee->date_of_joining?->toDateString(),
                'reporting_manager' => $employee->reportingManager ? self::cleanName($employee->reportingManager->full_name) : null,
                'hr_manager' => $employee->hrManager ? self::cleanName($employee->hrManager->full_name) : null,
                'official_email' => $employee->official_email,
                'weekly_off' => $employee->weekly_off ?? [],
            ],
            // Masked: the phone should never hold a full account number.
            'bank' => $bank ? [
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number ? str_repeat('•', max(strlen($bank->account_number) - 4, 0)).substr($bank->account_number, -4) : null,
                'ifsc' => $bank->ifsc,
            ] : null,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $this->user($request);

        if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.', 'errors' => ['current_password' => ['Current password is incorrect.']]], 422);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return response()->json(['ok' => true, 'message' => 'Password changed.']);
    }

    /** @return array<string, mixed> */
    public static function userSummary(User $user): array
    {
        $employee = $user->employee;

        return [
            'name' => self::cleanName($employee?->full_name ?? $user->name),
            'employee_code' => $employee?->employee_code ?? $user->employee_code,
            'email' => $user->email,
            'designation' => $employee?->designation?->name,
            'department' => $employee?->department?->name,
            'must_change_password' => (bool) $user->must_change_password,
            'is_approver' => ApprovalController::isApprover($user),
            // Has people reporting to them → "My Team" tab.
            'is_manager' => $employee?->directReports()->exists() ?? false,
        ];
    }

    public static function cleanName(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $name));
    }
}
