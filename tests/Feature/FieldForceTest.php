<?php

namespace Tests\Feature;

use App\Filament\Pages\Organogram;
use App\Filament\Resources\PositionResource;
use App\Filament\Resources\PositionResource\Pages\ListPositions;
use App\Models\Company;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Geography;
use App\Models\GeoLevel;
use App\Models\HierarchyLevel;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Services\PositionAssignmentService;
use App\Support\FieldForce;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FieldForceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->company = Company::create(['name' => 'Pharma Co', 'code' => 'PH', 'is_active' => true]);
    }

    private function makeEmployee(string $code, ?int $managerId = null): Employee
    {
        return Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'last_name' => 'Person',
            'official_email' => strtolower($code).'@example.test',
            'company_id' => $this->company->id,
            'date_of_joining' => now()->subYear(),
            'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $managerId,
        ]);
    }

    private function makeUser(Employee $employee, string $role): User
    {
        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->full_name,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function enableFieldForce(): void
    {
        $this->company->update(['has_field_force' => true]);
    }

    /** NSM > ZBM > RBM > ABM > two MR seats, all vacant. */
    private function buildStructure(): array
    {
        HierarchyLevel::loadPharmaDefaults();
        $division = Division::create(['name' => 'Cardio', 'code' => 'CD', 'company_id' => $this->company->id]);

        $geo = [];
        $parent = null;
        foreach (['ZONE' => 'West', 'REGION' => 'Mumbai', 'AREA' => 'Thane', 'HQ' => 'Thane HQ'] as $level => $name) {
            $parent = $geo[$level] = Geography::create([
                'geo_level_id' => GeoLevel::where('code', $level)->value('id'),
                'parent_id' => $parent?->id,
                'name' => $name,
                'code' => strtoupper(str_replace(' ', '', $name)),
            ]);
        }
        $geo['HQ2'] = Geography::create(['geo_level_id' => GeoLevel::where('code', 'HQ')->value('id'), 'parent_id' => $geo['AREA']->id, 'name' => 'Kalyan HQ', 'code' => 'KALYAN']);

        $level = fn (string $short) => HierarchyLevel::where('short_name', $short)->value('id');
        $seat = fn (string $code, string $short, ?Position $parent, ?Geography $g = null) => Position::create([
            'code' => $code, 'title' => $code, 'hierarchy_level_id' => $level($short),
            'parent_position_id' => $parent?->id, 'geography_id' => $g?->id, 'division_id' => $division->id,
            'company_id' => $this->company->id,
        ]);

        $nsm = $seat('NSM-01', 'NSM', null);
        $zbm = $seat('ZBM-W', 'ZBM', $nsm, $geo['ZONE']);
        $rbm = $seat('RBM-MUM', 'RBM', $zbm, $geo['REGION']);
        $abm = $seat('ABM-THN', 'ABM', $rbm, $geo['AREA']);
        $mr1 = $seat('MR-THN-01', 'MR', $abm, $geo['HQ']);
        $mr2 = $seat('MR-KYN-01', 'MR', $abm, $geo['HQ2']);

        return compact('division', 'geo', 'nsm', 'zbm', 'rbm', 'abm', 'mr1', 'mr2');
    }

    public function test_field_force_screens_are_hidden_until_a_company_enables_it(): void
    {
        $admin = $this->makeUser($this->makeEmployee('HR1'), 'HR Admin');
        $this->actingAs($admin);

        $this->assertFalse(FieldForce::enabled());
        $this->assertFalse(PositionResource::canViewAny());
        $this->get('/admin/positions')->assertForbidden();

        $this->enableFieldForce();

        $this->assertTrue(FieldForce::enabled());
        $this->assertTrue(PositionResource::canViewAny());
        foreach (['/admin/positions', '/admin/divisions', '/admin/geographies', '/admin/geo-levels', '/admin/hierarchy-levels'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_corporate_employee_form_is_unchanged_when_field_force_is_off(): void
    {
        $admin = $this->makeUser($this->makeEmployee('HR1'), 'Super Admin');
        $employee = $this->makeEmployee('E1');
        $this->actingAs($admin);

        $this->get("/admin/employees/{$employee->id}/edit")->assertOk()->assertDontSee('Workforce Type');

        $this->enableFieldForce();
        FieldForce::flush();

        $this->get("/admin/employees/{$employee->id}/edit")->assertOk()->assertSee('Workforce Type');
    }

    public function test_pharma_defaults_load_once_without_touching_existing_levels(): void
    {
        GeoLevel::create(['name' => 'Country', 'code' => 'COUNTRY', 'depth' => 1]);

        HierarchyLevel::loadPharmaDefaults();
        $again = HierarchyLevel::loadPharmaDefaults();

        $this->assertSame(0, $again);
        $this->assertSame('Country', GeoLevel::where('depth', 1)->value('name'));
        $this->assertSame(5, HierarchyLevel::count());
    }

    public function test_assigning_positions_syncs_reporting_lines_and_skips_vacant_seats(): void
    {
        $s = $this->buildStructure();
        $service = app(PositionAssignmentService::class);

        $rbm = $this->makeEmployee('RBM1');
        $abm = $this->makeEmployee('ABM1');
        $mr = $this->makeEmployee('MR1');

        $service->assign($s['rbm'], $rbm);
        $service->assign($s['mr1'], $mr);

        // ABM seat vacant: the MR reports to the RBM.
        $this->assertSame($rbm->id, $mr->fresh()->reporting_manager_id);
        $this->assertSame('field', $mr->fresh()->workforce_type);
        $this->assertSame($s['geo']['HQ']->id, $mr->fresh()->headquarter_id);
        $this->assertSame($s['division']->id, $mr->fresh()->division_id);

        // ABM joins: the MR now reports to the ABM, the ABM to the RBM.
        $service->assign($s['abm'], $abm);
        $this->assertSame($abm->id, $mr->fresh()->reporting_manager_id);
        $this->assertSame($rbm->id, $abm->fresh()->reporting_manager_id);

        // ABM leaves: the team falls back to the RBM, the seat shows vacant.
        $service->vacate($s['abm'], now());
        $this->assertSame($rbm->id, $mr->fresh()->reporting_manager_id);
        $this->assertTrue($s['abm']->fresh()->isVacant());
        $this->assertNotNull($s['abm']->fresh()->vacant_since);
        $this->assertNotNull(PositionAssignment::where('employee_id', $abm->id)->value('end_date'));
    }

    public function test_transfer_vacates_the_old_seat_and_keeps_history(): void
    {
        $s = $this->buildStructure();
        $service = app(PositionAssignmentService::class);
        $mr = $this->makeEmployee('MR1');

        $service->assign($s['mr1'], $mr, now()->subMonth());
        $service->assign($s['mr2'], $mr, now(), 'transfer');

        $this->assertTrue($s['mr1']->fresh()->isVacant());
        $this->assertSame($mr->id, $s['mr2']->fresh()->employee_id);
        $this->assertSame($s['geo']['HQ2']->id, $mr->fresh()->headquarter_id);
        $this->assertSame(2, PositionAssignment::where('employee_id', $mr->id)->count());
        $this->assertSame(1, PositionAssignment::where('employee_id', $mr->id)->whereNull('end_date')->count());
    }

    public function test_sync_off_leaves_reporting_manager_alone(): void
    {
        $s = $this->buildStructure();
        $service = app(PositionAssignmentService::class);
        $hoBoss = $this->makeEmployee('HO1');
        $rbm = $this->makeEmployee('RBM1');
        $mr = $this->makeEmployee('MR1', $hoBoss->id);

        $service->assign($s['rbm'], $rbm, syncReportingManagers: false);
        $service->assign($s['mr1'], $mr, syncReportingManagers: false);

        $this->assertSame($hoBoss->id, $mr->fresh()->reporting_manager_id);
    }

    public function test_position_and_geography_cycles_are_detected(): void
    {
        $s = $this->buildStructure();

        $this->assertTrue($s['zbm']->wouldCreateCycle($s['mr1']->id));
        $this->assertFalse($s['mr1']->wouldCreateCycle($s['rbm']->id));
        $this->assertTrue($s['geo']['ZONE']->wouldCreateCycle($s['geo']['HQ']->id));
        $this->assertSame('West > Mumbai > Thane > Thane HQ', $s['geo']['HQ']->path);
    }

    public function test_assign_action_from_positions_list(): void
    {
        $this->enableFieldForce();
        $s = $this->buildStructure();
        $admin = $this->makeUser($this->makeEmployee('HR1'), 'HR Admin');
        $mr = $this->makeEmployee('MR1');
        $this->actingAs($admin);

        Livewire::test(ListPositions::class)
            ->assertSee('VACANT')
            ->callTableAction('assign', $s['mr1'], data: [
                'employee_id' => $mr->id,
                'start_date' => now()->toDateString(),
                'reason' => 'joining',
                'sync_reporting' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($mr->id, $s['mr1']->fresh()->employee_id);
    }

    public function test_organogram_shows_whole_org_to_hr_and_own_team_to_managers(): void
    {
        $ceo = $this->makeEmployee('CEO');
        $vp = $this->makeEmployee('VPSALES', $ceo->id);
        $rep = $this->makeEmployee('REP', $vp->id);
        $other = $this->makeEmployee('FINANCE', $ceo->id);

        $this->actingAs($this->makeUser($this->makeEmployee('HR1'), 'HR Admin'));
        Livewire::test(Organogram::class)
            ->assertOk()
            ->assertSee('VPSALES Person')
            ->assertSee('FINANCE Person')
            ->assertDontSee('Field Positions');

        $this->actingAs($this->makeUser($vp, 'Manager'));
        Livewire::test(Organogram::class)
            ->assertOk()
            ->assertSee('REP Person')
            ->assertDontSee('FINANCE Person')
            ->assertSee('Your reporting line');
    }

    public function test_organogram_position_and_geography_views(): void
    {
        $this->enableFieldForce();
        $s = $this->buildStructure();
        app(PositionAssignmentService::class)->assign($s['mr1'], $this->makeEmployee('MR1'));

        $this->actingAs($this->makeUser($this->makeEmployee('HR1'), 'HR Admin'));

        Livewire::test(Organogram::class)
            ->call('setMode', 'positions')
            ->assertSee('MR1 Person')
            ->assertSee('VACANT')
            ->assertSee('5 vacant')
            ->call('setMode', 'geography')
            ->assertSee('Thane HQ')
            ->assertSee('1/1 positions filled')
            ->set('search', 'kalyan')
            ->assertSee('Kalyan HQ')
            ->assertDontSee('Thane HQ');
    }
}
