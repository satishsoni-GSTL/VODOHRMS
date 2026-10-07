<?php

namespace Tests\Feature;

use App\Filament\Resources\AttendanceRegularizationResource\Pages\CreateAttendanceRegularization;
use App\Filament\Resources\EmployeeLoanResource\Pages\EditEmployeeLoan;
use App\Filament\Resources\WorkFromHomeRequestResource\Pages\CreateWorkFromHomeRequest;
use App\Models\AttendanceRegularization;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Employee" field on self-service forms is disabled for employees, but a disabled field
 * can still be changed from the browser console (Livewire state). The server must ignore it.
 */
class FormTamperingTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Carbon::setTestNow('2026-10-07 10:00');

        $manager = $this->makeUser('TMGR', 'Manager');
        $this->employee = $this->makeUser('TEMP1', 'Employee', $manager->employee_id);
        $this->colleague = $this->makeUser('TEMP2', 'Employee', $manager->employee_id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $code, string $role, ?int $managerId = null): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $e = Employee::create([
            'employee_code' => $code, 'first_name' => $code, 'official_email' => strtolower($code).'@x.test',
            'company_id' => $company->id, 'date_of_joining' => '2025-01-01', 'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $managerId, 'weekly_off' => ['saturday', 'sunday'],
        ]);
        $u = User::create(['employee_id' => $e->id, 'employee_code' => $code, 'name' => $code, 'email' => $e->official_email, 'password' => bcrypt('x'), 'is_active' => true]);
        $u->assignRole($role);

        return $u->fresh();
    }

    public function test_employee_cannot_file_a_regularization_for_someone_else_via_the_console(): void
    {
        $this->actingAs($this->employee);

        Livewire::test(CreateAttendanceRegularization::class)
            ->fillForm([
                'attendance_date' => '2026-10-06',
                'request_type' => 'missing_punch',
                'requested_values' => ['first_in' => '09:30', 'last_out' => '18:30'],
                'reason' => 'Forgot to punch',
            ])
            // What a tampering user does in the browser console: overwrite the disabled field.
            ->set('data.employee_id', $this->colleague->employee_id)
            ->call('create')
            ->assertHasNoFormErrors();

        $request = AttendanceRegularization::sole();
        $this->assertSame($this->employee->employee_id, $request->employee_id, 'The request must be filed for the signed-in employee only.');

        $log = AuditLog::where('action', 'tampering_blocked')->sole();
        $this->assertSame($this->employee->id, $log->user_id);
        $this->assertStringContainsString('CreateAttendanceRegularization', $log->reason);
    }

    public function test_hr_can_still_file_on_behalf_of_an_employee(): void
    {
        $hr = $this->makeUser('THR', 'HR Admin');
        $this->actingAs($hr);

        Livewire::test(CreateWorkFromHomeRequest::class)
            ->fillForm([
                'employee_id' => $this->colleague->employee_id,
                'from_date' => '2026-10-08',
                'to_date' => '2026-10-08',
                'reason' => 'Filed by HR',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->colleague->employee_id, \App\Models\WorkFromHomeRequest::sole()->employee_id);
        $this->assertSame(0, AuditLog::where('action', 'tampering_blocked')->count());
    }

    public function test_employee_cannot_move_their_loan_to_another_employee_when_editing(): void
    {
        $loan = EmployeeLoan::create([
            'employee_id' => $this->employee->employee_id, 'type' => EmployeeLoan::TYPE_SALARY_ADVANCE,
            'requested_amount' => 5000, 'reason' => 'Medical', 'request_date' => '2026-10-07', 'status' => EmployeeLoan::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee);

        Livewire::test(EditEmployeeLoan::class, ['record' => $loan->getRouteKey()])
            ->set('data.employee_id', $this->colleague->employee_id)
            ->call('save');

        $this->assertSame($this->employee->employee_id, $loan->fresh()->employee_id);
    }
}
