<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\BirthdayNotification;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateSmokeTest extends TestCase
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
            'dob' => now()->subYears(25)->toDateString(),
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

    public function test_editing_a_template_changes_the_rendered_mail(): void
    {
        $this->seed(NotificationTemplateSeeder::class);

        $celebrant = $this->makeUser('BDAY2', 'Employee');

        NotificationTemplate::where('key', 'birthday_self')->update([
            'subject' => 'Custom Subject for {employee_name}',
            'body' => 'Custom body line for {employee_name}',
        ]);

        $notification = new BirthdayNotification($celebrant->employee);
        $mail = $notification->toMail($celebrant);

        $this->assertEquals('Custom Subject for '.$celebrant->employee->full_name, $mail->subject);
        $this->assertContains('Custom body line for '.$celebrant->employee->full_name, $mail->introLines);
    }

    public function test_missing_template_falls_back_to_hardcoded_default(): void
    {
        // NotificationTemplateSeeder is deliberately not run here — the templates table is empty.
        $celebrant = $this->makeUser('BDAY3', 'Employee');

        $notification = new BirthdayNotification($celebrant->employee);
        $mail = $notification->toMail($celebrant);

        $this->assertEquals('Happy Birthday!', $mail->subject);
        $this->assertContains('Happy Birthday, '.$celebrant->employee->full_name.'!', $mail->introLines);
    }

    public function test_hr_admin_can_view_and_edit_templates(): void
    {
        $this->seed(NotificationTemplateSeeder::class);

        $hrAdmin = $this->makeUser('HR900', 'HR Admin');

        $this->actingAs($hrAdmin, 'web');
        $this->get('/admin/notification-templates')->assertStatus(200);

        $template = NotificationTemplate::where('key', 'payslip_ready')->firstOrFail();
        $this->get("/admin/notification-templates/{$template->id}/edit")->assertStatus(200);
    }

    public function test_plain_employee_is_blocked_from_templates(): void
    {
        $this->seed(NotificationTemplateSeeder::class);

        $employee = $this->makeUser('EMP900', 'Employee');

        $this->actingAs($employee, 'web');
        $this->get('/admin/notification-templates')->assertStatus(403);
        $this->get('/admin/notification-templates/create')->assertStatus(404);
    }
}
