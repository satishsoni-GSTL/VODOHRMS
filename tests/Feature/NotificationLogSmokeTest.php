<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\PayslipReadyNotification;
use App\Services\LeaveApplicationService;
use App\Services\LeaveBalanceService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase4Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLogSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase4Seeder::class);
    }

    private function makeUser(string $employeeCode, string $role, ?int $reportingManagerId = null): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $employeeCode,
            'first_name' => $employeeCode,
            'official_email' => strtolower($employeeCode).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now()->subYear(),
            'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $reportingManagerId,
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

    public function test_a_real_notification_send_is_logged_as_sent(): void
    {
        // Deliberately no Notification::fake() here — phpunit.xml sets MAIL_MAILER=array and
        // QUEUE_CONNECTION=sync, so the send actually runs through NotificationSending/Sent
        // for real, which is what the global listener in AppServiceProvider hooks into.
        $manager = $this->makeUser('MGR900', 'Manager');
        $employeeUser = $this->makeUser('EMP900', 'Employee', $manager->employee_id);

        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();
        app(LeaveBalanceService::class)->credit($employeeUser->employee, $leaveType, now()->year, 12, 'Annual credit');

        app(LeaveApplicationService::class)->apply(
            $employeeUser->employee, $leaveType, Carbon::today()->addDays(2), Carbon::today()->addDays(2),
            false, null, 'Personal work', null,
        );

        $log = NotificationLog::where('type', 'ApprovalActionRequiredNotification')
            ->where('recipient_email', $manager->email)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('sent', $log->status);
        $this->assertEquals('mail', $log->channel);
    }

    public function test_failed_hook_marks_the_matching_log_row_as_failed(): void
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $run = \App\Models\PayrollRun::create(['payroll_month' => now()->format('Y-m'), 'company_id' => $company->id, 'status' => 'finalized']);

        $notification = new PayslipReadyNotification($run);
        $notification->id = 'test-uuid-1234';

        NotificationLog::create([
            'notification_id' => $notification->id,
            'type' => 'PayslipReadyNotification',
            'recipient_email' => 'someone@vodohrms.local',
            'channel' => 'mail',
            'status' => 'pending',
        ]);

        $notification->failed(new \Exception('Connection could not be established with host smtp.office365.com'));

        $log = NotificationLog::where('notification_id', 'test-uuid-1234')->firstOrFail();
        $this->assertEquals('failed', $log->status);
        $this->assertStringContainsString('smtp.office365.com', $log->error);
    }
}
