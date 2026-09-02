<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkFromHomeRequestResource\Pages\ViewWorkFromHomeRequest;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\AttendanceRegularizationService;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegularizationMarksPresentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
    }

    private function makeUser(string $code, string $role, ?int $managerId = null): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYear(),
            'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $managerId,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function approveFully(AttendanceRegularization $regularization, User $manager, User $hr): void
    {
        $workflow = app(ApprovalWorkflowService::class);
        $workflow->act($regularization->approvalInstance, $manager, 'approve');
        $workflow->act($regularization->approvalInstance->fresh(), $hr, 'approve');
    }

    public function test_approving_a_regularization_turns_an_absent_day_present(): void
    {
        $manager = $this->makeUser('MGR100', 'Manager');
        $employeeUser = $this->makeUser('EMP100', 'Employee', $manager->employee_id);
        $hr = $this->makeUser('HR100', 'HR Admin');

        $date = Carbon::today()->subDays(3);

        // The register already has the day down as Absent (daily cron / import).
        Attendance::create([
            'employee_id' => $employeeUser->employee_id,
            'attendance_date' => $date->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
            'source' => 'system',
        ]);

        $regularization = app(AttendanceRegularizationService::class)->request(
            $employeeUser->employee, $date, 'missing_punch',
            ['first_in' => '09:15', 'last_out' => '18:20'], 'Forgot to punch', null,
        );

        $this->approveFully($regularization, $manager, $hr);

        $attendance = Attendance::where('employee_id', $employeeUser->employee_id)
            ->where('attendance_date', $date->toDateString())
            ->first();

        $this->assertSame(Attendance::STATUS_PRESENT, $attendance->status);
        $this->assertStringStartsWith('09:15', $attendance->first_in);
    }

    public function test_regularization_with_an_explicit_status_keeps_that_status(): void
    {
        $manager = $this->makeUser('MGR101', 'Manager');
        $employeeUser = $this->makeUser('EMP101', 'Employee', $manager->employee_id);
        $hr = $this->makeUser('HR101', 'HR Admin');

        $date = Carbon::today()->subDays(4);

        Attendance::create([
            'employee_id' => $employeeUser->employee_id,
            'attendance_date' => $date->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
            'source' => 'system',
        ]);

        $regularization = app(AttendanceRegularizationService::class)->request(
            $employeeUser->employee, $date, 'on_duty',
            ['status' => Attendance::STATUS_ON_DUTY], 'Client site visit, no punches', null,
        );

        $this->approveFully($regularization, $manager, $hr);

        $attendance = Attendance::where('employee_id', $employeeUser->employee_id)
            ->where('attendance_date', $date->toDateString())
            ->first();

        $this->assertSame(Attendance::STATUS_ON_DUTY, $attendance->status);
    }

    public function test_regularization_does_not_disturb_an_approved_leave_day(): void
    {
        $manager = $this->makeUser('MGR102', 'Manager');
        $employeeUser = $this->makeUser('EMP102', 'Employee', $manager->employee_id);
        $hr = $this->makeUser('HR102', 'HR Admin');

        $date = Carbon::today()->subDays(5);

        Attendance::create([
            'employee_id' => $employeeUser->employee_id,
            'attendance_date' => $date->toDateString(),
            'status' => Attendance::STATUS_LEAVE,
            'source' => 'leave',
        ]);

        $regularization = app(AttendanceRegularizationService::class)->request(
            $employeeUser->employee, $date, 'missing_punch',
            ['first_in' => '09:00', 'last_out' => '18:00'], 'Actually worked', null,
        );

        $this->approveFully($regularization, $manager, $hr);

        $attendance = Attendance::where('employee_id', $employeeUser->employee_id)
            ->where('attendance_date', $date->toDateString())
            ->first();

        $this->assertSame(Attendance::STATUS_LEAVE, $attendance->status);
    }

    public function test_wfh_view_page_exposes_an_approve_action_to_the_approver(): void
    {
        $manager = $this->makeUser('MGR103', 'Manager');
        $employeeUser = $this->makeUser('EMP103', 'Employee', $manager->employee_id);

        $request = app(WorkFromHomeService::class)->request(
            $employeeUser->employee,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(2),
            'Plumber visit',
        );

        Livewire::actingAs($manager)
            ->test(ViewWorkFromHomeRequest::class, ['record' => $request->getKey()])
            ->assertActionVisible('approve');

        // The requester is not an approver, so they see no approve button.
        Livewire::actingAs($employeeUser)
            ->test(ViewWorkFromHomeRequest::class, ['record' => $request->getKey()])
            ->assertActionHidden('approve');
    }
}
