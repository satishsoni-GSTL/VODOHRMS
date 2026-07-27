<?php

namespace Tests\Feature;

use App\Filament\Resources\PolicyDocumentResource\Pages\CreatePolicyDocument;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PolicyDocument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PolicyDocumentSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $employeeCode, string $role): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employeeCode,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_hr_admin_can_create_policy_document_and_employee_can_only_view(): void
    {
        Storage::fake('local');

        $hrAdmin = $this->makeUser('HR800', 'HR Admin');
        $employee = $this->makeUser('EMP800', 'Employee');

        $this->actingAs($hrAdmin, 'web');

        $file = UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf');

        Livewire::test(CreatePolicyDocument::class)
            ->fillForm([
                'title' => 'Employee Handbook',
                'description' => 'Company policies and conduct guidelines.',
                'file_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $policy = PolicyDocument::where('title', 'Employee Handbook')->firstOrFail();
        $this->assertEquals($hrAdmin->id, $policy->uploaded_by);
        $this->assertTrue($policy->is_active);

        $this->actingAs($employee, 'web');
        $this->get('/admin/policy-documents')->assertStatus(200);
        $this->get('/admin/policy-documents/create')->assertStatus(403);
    }

    public function test_download_route_respects_active_flag(): void
    {
        Storage::fake('local');

        $hrAdmin = $this->makeUser('HR810', 'HR Admin');
        $employee = $this->makeUser('EMP810', 'Employee');

        $active = PolicyDocument::create([
            'title' => 'Leave Policy',
            'file_path' => 'policy-documents/leave-policy.pdf',
            'file_name' => 'leave-policy.pdf',
            'uploaded_by' => $hrAdmin->id,
            'is_active' => true,
        ]);
        Storage::disk('local')->put($active->file_path, 'dummy pdf content');

        $draft = PolicyDocument::create([
            'title' => 'Draft Policy',
            'file_path' => 'policy-documents/draft-policy.pdf',
            'file_name' => 'draft-policy.pdf',
            'uploaded_by' => $hrAdmin->id,
            'is_active' => false,
        ]);
        Storage::disk('local')->put($draft->file_path, 'dummy draft content');

        $this->actingAs($employee, 'web');
        $this->get(route('policy-documents.download', $active))->assertStatus(200);
        $this->get(route('policy-documents.download', $draft))->assertStatus(404);

        $this->actingAs($hrAdmin, 'web');
        $this->get(route('policy-documents.download', $draft))->assertStatus(200);
    }
}
