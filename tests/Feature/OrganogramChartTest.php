<?php

namespace Tests\Feature;

use App\Filament\Pages\Organogram;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganogramChartTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->company = Company::create(['name' => 'Acme', 'code' => 'AC', 'is_active' => true]);
    }

    private function emp(string $code, ?int $managerId = null, ?int $departmentId = null): Employee
    {
        return Employee::create([
            'employee_code' => $code, 'first_name' => $code, 'last_name' => 'Person',
            'official_email' => strtolower($code).'@example.test', 'company_id' => $this->company->id,
            'date_of_joining' => now()->subYear(), 'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $managerId, 'department_id' => $departmentId,
        ]);
    }

    private function user(Employee $employee, string $role): User
    {
        $user = User::create([
            'employee_id' => $employee->id, 'employee_code' => $employee->employee_code, 'name' => $employee->full_name,
            'email' => $employee->official_email, 'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_chart_list_focus_and_legend(): void
    {
        $sales = Department::create(['company_id' => $this->company->id, 'name' => 'Sales', 'code' => 'SAL', 'is_active' => true]);
        $ceo = $this->emp('CEO');
        $vp = $this->emp('VP', $ceo->id, $sales->id);
        foreach (['R1', 'R2', 'R3'] as $code) {
            $this->emp($code, $vp->id, $sales->id);
        }
        $this->emp('CFO', $ceo->id);

        $this->actingAs($this->user($this->emp('HR1'), 'HR Admin'));

        Livewire::test(Organogram::class)
            ->assertOk()
            ->assertSee('class="oc-card', false)              // top-down chart by default
            ->assertSee('class="oc-stack"', false)            // VP's three leaf reports stack in a column
            ->assertSee('Sales')                              // department legend
            ->assertSee('Open employee record', false)        // HR can open records
            ->call('focusOn', 'e'.$vp->id)
            ->assertSee('Whole organisation')
            ->assertSee('R1 Person')
            ->assertDontSee('CFO Person')
            ->call('clearFocus')
            ->assertSee('CFO Person')
            ->call('setLayout', 'list')
            ->assertSee('class="org-tree"', false)
            ->assertDontSee('class="oc-card', false);
    }

    public function test_manager_sees_own_card_highlighted(): void
    {
        $ceo = $this->emp('CEO');
        $mgr = $this->emp('MGR', $ceo->id);
        $this->emp('REP', $mgr->id);

        $this->actingAs($this->user($mgr, 'Manager'));

        Livewire::test(Organogram::class)
            ->assertSee('(You)')
            ->assertSee('REP Person')
            ->assertDontSee('Open employee record', false);
    }
}
