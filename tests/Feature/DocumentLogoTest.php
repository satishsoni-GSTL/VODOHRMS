<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\DocumentLogo;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentLogoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->company = Company::create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    }

    private function makeEmployee(string $code, array $attributes = []): Employee
    {
        return Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@x.test',
            'company_id' => $this->company->id,
            'date_of_joining' => '2024-01-01',
            'status' => Employee::STATUS_ACTIVE,
            ...$attributes,
        ]);
    }

    private function dataUriFor(string $path): string
    {
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    private function actingAsUserWith(array $permissions): void
    {
        $role = Role::create(['name' => 'Logo Test Role', 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::create(['name' => 'Logo Tester', 'email' => 'logo-tester@x.test', 'password' => 'secret', 'is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user);
    }

    public function test_employee_choice_beats_company_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/ho.png', file_get_contents(public_path('images/globalspace-logo.png')));
        $this->company->update(['logo_path' => 'company-logos/ho.png']);

        $this->assertSame(
            $this->dataUriFor(public_path('images/makebot-logo.png')),
            DocumentLogo::dataUri($this->makeEmployee('EMP1', ['document_logo' => 'makebot'])),
        );
    }

    public function test_company_logo_is_used_when_no_choice_is_set(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/ho.png', file_get_contents(public_path('images/makebot-logo.png')));
        $this->company->update(['logo_path' => 'company-logos/ho.png']);

        $this->assertSame(
            $this->dataUriFor(public_path('images/makebot-logo.png')),
            DocumentLogo::dataUri($this->makeEmployee('EMP2')),
        );
    }

    public function test_falls_back_to_the_light_background_globalspace_logo(): void
    {
        Storage::fake('public');

        $this->assertSame(
            $this->dataUriFor(public_path('images/globalspace-logo-light.png')),
            DocumentLogo::dataUri($this->makeEmployee('EMP3')),
        );
    }

    public function test_hr_can_set_the_logo_for_selected_employees(): void
    {
        $this->actingAsUserWith(['employee.view', 'employee.edit']);
        $picked = $this->makeEmployee('EMP4');
        $other = $this->makeEmployee('EMP5');

        Livewire::test(ListEmployees::class)
            ->callTableBulkAction('setDocumentLogo', [$picked], ['document_logo' => 'makebot'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('makebot', $picked->fresh()->document_logo);
        $this->assertNull($other->fresh()->document_logo);

        Livewire::test(ListEmployees::class)
            ->callTableBulkAction('setDocumentLogo', [$picked], ['document_logo' => null]);

        $this->assertNull($picked->fresh()->document_logo);
    }

    public function test_bulk_action_needs_edit_permission(): void
    {
        $this->actingAsUserWith(['employee.view']);

        Livewire::test(ListEmployees::class)->assertTableBulkActionHidden('setDocumentLogo');
    }

    public function test_login_page_shows_both_logos(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('images/globalspace-logo-light.png')
            ->assertSee('images/makebot-logo.png');
    }
}
