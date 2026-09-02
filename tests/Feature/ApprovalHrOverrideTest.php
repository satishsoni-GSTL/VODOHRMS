<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalHrOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Carbon::setTestNow(Carbon::parse('2026-03-02'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            'weekly_off' => ['saturday', 'sunday'],
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

    public function test_hr_admin_can_act_on_a_wfh_request_routed_to_someone_else(): void
    {
        $manager = $this->makeUser('MGR200', 'Manager');
        $employeeUser = $this->makeUser('EMP200', 'Employee', $manager->employee_id);
        $hr = $this->makeUser('HR200', 'HR Admin'); // not in the reporting chain

        $request = app(WorkFromHomeService::class)->request(
            $employeeUser->employee,
            Carbon::parse('2026-03-04'),
            Carbon::parse('2026-03-04'),
            'Personal commitment',
        );

        $workflow = app(ApprovalWorkflowService::class);

        $this->assertTrue($workflow->canUserActOnInstance($request->approvalInstance, $hr));
        $this->assertFalse($workflow->canUserActOnInstance($request->approvalInstance, $employeeUser));

        $workflow->act($request->approvalInstance, $hr, 'approve');

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_a_plain_employee_still_cannot_act(): void
    {
        $manager = $this->makeUser('MGR201', 'Manager');
        $employeeUser = $this->makeUser('EMP201', 'Employee', $manager->employee_id);
        $stranger = $this->makeUser('EMP202', 'Employee');

        $request = app(WorkFromHomeService::class)->request(
            $employeeUser->employee,
            Carbon::parse('2026-03-04'),
            Carbon::parse('2026-03-04'),
            'Personal commitment',
        );

        $this->assertFalse(
            app(ApprovalWorkflowService::class)->canUserActOnInstance($request->approvalInstance, $stranger)
        );
    }
}
