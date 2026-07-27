<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmploymentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Phase1SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeAdmin(): User
    {
        $company = Company::create(['name' => 'Head Office', 'code' => 'HO', 'is_active' => true]);
        $employee = Employee::create([
            'employee_code' => 'ADMIN001',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'official_email' => 'admin@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);
        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => 'System Admin',
            'email' => 'admin@vodohrms.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_login_page_loads(): void
    {
        $this->get('/admin/login')->assertStatus(200)->assertSee('Employee Code or Email');
    }

    public function test_authenticated_admin_can_view_dashboard(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'web');
        $this->get('/admin')->assertStatus(200);
    }

    public function test_authenticated_admin_can_view_employee_list(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'web');
        $this->get('/admin/employees')->assertStatus(200);
    }

    public function test_authenticated_admin_can_view_employee_import_page(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'web');
        $this->get('/admin/employees/import')->assertStatus(200)->assertSee('Bulk Import Employees');
    }

    public function test_authenticated_admin_can_view_employee_create_page(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'web');
        $this->get('/admin/employees/create')->assertStatus(200);
    }

    public function test_authenticated_admin_can_view_company_list(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'web');
        $this->get('/admin/companies')->assertStatus(200);
    }

    public function test_unauthenticated_user_redirected_from_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * @return array<array<string>>
     */
    public static function orgMasterRoutes(): array
    {
        return [
            ['/admin/branches'],
            ['/admin/locations'],
            ['/admin/departments'],
            ['/admin/sub-departments'],
            ['/admin/designations'],
            ['/admin/grades'],
            ['/admin/cost-centers'],
            ['/admin/employee-types'],
            ['/admin/employment-types'],
        ];
    }

    /**
     * @dataProvider orgMasterRoutes
     */
    public function test_authenticated_admin_can_view_org_master_list(string $route): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user, 'web');
        $this->get($route)->assertStatus(200);
    }

    public function test_admin_can_create_employee_end_to_end_via_form(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'web');

        $company = Company::first();
        $department = Department::create(['company_id' => $company->id, 'name' => 'Engineering', 'code' => 'ENG', 'is_active' => true]);
        $designation = Designation::create(['name' => 'Software Engineer', 'code' => 'SE', 'is_active' => true]);
        $employeeType = EmployeeType::create(['name' => 'Head Office', 'code' => 'HO', 'is_active' => true]);
        $employmentType = EmploymentType::create(['name' => 'Permanent', 'code' => 'PERM', 'is_active' => true]);

        Livewire::test(CreateEmployee::class)
            ->fillForm([
                'employee_code' => 'EMP-TEST-001',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'official_email' => 'jane.doe@vodohrms.local',
                'company_id' => $company->id,
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'employee_type_id' => $employeeType->id,
                'employment_type_id' => $employmentType->id,
                'date_of_joining' => now()->toDateString(),
                'status' => Employee::STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-TEST-001',
            'official_email' => 'jane.doe@vodohrms.local',
        ]);
    }

    public function test_circular_reporting_manager_is_rejected_by_form(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'web');

        $company = Company::first();
        $manager = Employee::create([
            'employee_code' => 'MGR-001', 'first_name' => 'Manager', 'official_email' => 'manager@vodohrms.local',
            'company_id' => $company->id, 'date_of_joining' => now(), 'status' => Employee::STATUS_ACTIVE,
        ]);
        $report = Employee::create([
            'employee_code' => 'REP-001', 'first_name' => 'Report', 'official_email' => 'report@vodohrms.local',
            'company_id' => $company->id, 'date_of_joining' => now(), 'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $manager->id,
        ]);

        Livewire::test(EditEmployee::class, ['record' => $manager->id])
            ->fillForm(['reporting_manager_id' => $report->id])
            ->call('save')
            ->assertHasFormErrors(['reporting_manager_id']);
    }
}
