<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReprefixEmployeeCodesTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(string $code): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => strtolower($code).'@vodohrms.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        return $employee;
    }

    public function test_dry_run_changes_nothing(): void
    {
        $employee = $this->makeEmployee('LEG001');

        $this->artisan('employees:reprefix-codes')->assertSuccessful();

        $this->assertSame('LEG001', $employee->fresh()->employee_code);
    }

    public function test_commit_reprefixes_employee_and_user_and_leaves_others_alone(): void
    {
        $legacy = $this->makeEmployee('LEG042');
        $other = $this->makeEmployee('EMP900');

        $this->artisan('employees:reprefix-codes --commit')->assertSuccessful();

        $this->assertSame('GS042', $legacy->fresh()->employee_code);
        $this->assertSame('GS042', User::where('employee_id', $legacy->id)->value('employee_code'));
        $this->assertSame('EMP900', $other->fresh()->employee_code);
    }

    public function test_a_clashing_target_code_is_skipped(): void
    {
        $legacy = $this->makeEmployee('LEG007');
        $this->makeEmployee('GS007');

        $this->artisan('employees:reprefix-codes --commit')->assertSuccessful();

        $this->assertSame('LEG007', $legacy->fresh()->employee_code);
    }

    public function test_custom_prefixes_are_honoured(): void
    {
        $employee = $this->makeEmployee('OLD12');

        $this->artisan('employees:reprefix-codes --from=OLD --to=NEW --commit')->assertSuccessful();

        $this->assertSame('NEW12', $employee->fresh()->employee_code);
    }
}
