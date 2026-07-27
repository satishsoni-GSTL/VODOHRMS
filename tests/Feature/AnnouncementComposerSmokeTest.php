<?php

namespace Tests\Feature;

use App\Filament\Pages\SendAnnouncement;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementComposerSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $employeeCode, string $role, int $companyId): User
    {
        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $companyId,
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

    public function test_hr_admin_can_send_an_announcement_scoped_to_a_company(): void
    {
        $companyA = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $companyB = Company::firstOrCreate(['code' => 'BR'], ['name' => 'Branch Office', 'is_active' => true]);

        $hrAdmin = $this->makeUser('HR910', 'HR Admin', $companyA->id);
        $employeeA = $this->makeUser('EMPA910', 'Employee', $companyA->id);
        $employeeB = $this->makeUser('EMPB910', 'Employee', $companyB->id);

        $this->actingAs($hrAdmin, 'web');

        Livewire::test(SendAnnouncement::class)
            ->fillForm([
                'subject' => 'Office Closure Notice',
                'message' => 'The office will be closed this Friday for maintenance.',
                'recipient_scope' => 'company',
                'company_id' => $companyA->id,
            ])
            ->callAction('send');

        $this->assertTrue(
            NotificationLog::where('type', 'AnnouncementNotification')
                ->where('recipient_email', $employeeA->email)
                ->exists()
        );
        $this->assertFalse(
            NotificationLog::where('type', 'AnnouncementNotification')
                ->where('recipient_email', $employeeB->email)
                ->exists()
        );
    }

    public function test_plain_employee_cannot_access_the_announcement_composer(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $employee = $this->makeUser('EMP920', 'Employee', $company->id);

        $this->actingAs($employee, 'web');
        $this->get('/admin/send-announcement')->assertStatus(403);
    }
}
