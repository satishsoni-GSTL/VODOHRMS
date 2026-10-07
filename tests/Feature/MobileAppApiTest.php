<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\OptionalHolidayLimit;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end checks of the employee mobile-app API: each feature goes through the same
 * services/workflow as the web panel.
 */
class MobileAppApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    private string $managerToken;

    private string $employeeToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        $this->seed(Phase3Seeder::class);
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00'));

        $this->manager = $this->makeUser('MGR900', 'Manager');
        $this->employee = $this->makeUser('EMP900', 'Employee', $this->manager->employee_id);
        $this->managerToken = $this->tokenFor('MGR900');
        $this->employeeToken = $this->tokenFor('EMP900');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $code, string $role, ?int $managerId = null): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => '2025-01-01',
            'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $managerId,
            'weekly_off' => ['saturday', 'sunday'],
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $employee->official_email,
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function tokenFor(string $code): string
    {
        return $this->postJson('/api/mobile/login', ['login' => $code, 'password' => 'secret123'])->assertOk()->json('token');
    }

    private function asEmployee()
    {
        return $this->withToken($this->employeeToken);
    }

    private function asManager()
    {
        return $this->withToken($this->managerToken);
    }

    public function test_dashboard_and_profile(): void
    {
        $this->asEmployee()->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('user.employee_code', 'EMP900')
            ->assertJsonPath('user.is_approver', false)
            ->assertJsonStructure(['today', 'leave_balances', 'my_pending', 'approvals_waiting', 'upcoming_holidays']);

        $this->asManager()->getJson('/api/mobile/me')->assertJsonPath('user.is_approver', true);

        $this->asEmployee()->getJson('/api/mobile/profile')
            ->assertOk()
            ->assertJsonPath('job.employee_code', 'EMP900')
            ->assertJsonPath('job.reporting_manager', 'MGR900');
    }

    public function test_attendance_month(): void
    {
        Attendance::create(['employee_id' => $this->employee->employee_id, 'attendance_date' => '2026-10-01', 'first_in' => '09:30:00', 'last_out' => '18:30:00', 'status' => Attendance::STATUS_PRESENT]);

        $response = $this->asEmployee()->getJson('/api/mobile/attendance?month=2026-10')->assertOk();

        $this->assertCount(31, $response->json('days'));
        $this->assertSame('P', $response->json('days.0.code'));
        $this->assertSame('09:30:00', $response->json('days.0.first_in'));
        $this->assertSame(1, $response->json('totals.present'));
    }

    public function test_leave_apply_and_manager_approves_from_the_app(): void
    {
        $lwp = LeaveType::where('code', 'LWP')->firstOrFail();

        $this->asEmployee()->getJson('/api/mobile/leave')->assertOk()->assertJsonFragment(['code' => 'LWP']);

        $this->asEmployee()->postJson('/api/mobile/leave/preview', ['from_date' => '2026-10-08', 'to_date' => '2026-10-12'])
            ->assertOk()->assertJsonPath('days', 3); // Thu, Fri, Mon

        $this->asEmployee()->postJson('/api/mobile/leave', [
            'leave_type_id' => $lwp->id, 'from_date' => '2026-10-08', 'to_date' => '2026-10-12', 'reason' => 'Travel',
        ])->assertCreated()->assertJsonPath('application.status', 'pending');

        $approvals = $this->asManager()->getJson('/api/mobile/approvals')->assertOk()->json('items');
        $this->assertCount(1, $approvals);
        $this->assertSame('leave', $approvals[0]['type']);

        $this->asManager()->postJson("/api/mobile/approvals/{$approvals[0]['id']}", ['action' => 'approve'])->assertOk();

        $this->assertSame(LeaveApplication::STATUS_APPROVED, LeaveApplication::first()->status);
        $this->assertCount(0, $this->asManager()->getJson('/api/mobile/approvals')->json('items'));
    }

    public function test_reject_needs_remarks_and_employee_sees_them(): void
    {
        $this->asEmployee()->postJson('/api/mobile/wfh', ['from_date' => '2026-10-08', 'to_date' => '2026-10-09', 'reason' => 'Plumber visit'])
            ->assertCreated();

        $id = $this->asManager()->getJson('/api/mobile/approvals')->json('items.0.id');

        $this->asManager()->postJson("/api/mobile/approvals/{$id}", ['action' => 'reject'])->assertStatus(422);
        $this->asManager()->postJson("/api/mobile/approvals/{$id}", ['action' => 'reject', 'remarks' => 'Team offsite that week'])->assertOk();

        $this->asEmployee()->getJson('/api/mobile/wfh')
            ->assertJsonPath('items.0.status', WorkFromHomeRequest::STATUS_REJECTED)
            ->assertJsonPath('items.0.remarks', 'Team offsite that week')
            ->assertJsonPath('items.0.can_reapply', true);
    }

    public function test_a_colleague_cannot_act_on_someone_elses_request(): void
    {
        $this->asEmployee()->postJson('/api/mobile/wfh', ['from_date' => '2026-10-08', 'to_date' => '2026-10-08', 'reason' => 'x'])->assertCreated();
        $instanceId = WorkFromHomeRequest::first()->approval_instance_id;

        $this->makeUser('EMP901', 'Employee', $this->manager->employee_id);
        $this->withToken($this->tokenFor('EMP901'))
            ->postJson("/api/mobile/approvals/{$instanceId}", ['action' => 'approve'])
            ->assertStatus(422);
    }

    public function test_regularization_with_attachment(): void
    {
        $this->asEmployee()->post('/api/mobile/regularizations', [
            'attendance_date' => '2026-10-06',
            'request_type' => 'missing_punch',
            'first_in' => '09:40',
            'last_out' => '18:20',
            'reason' => 'Forgot to punch out',
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->asEmployee()->getJson('/api/mobile/regularizations')
            ->assertJsonPath('items.0.first_in', '09:40:00')
            ->assertJsonPath('items.0.status', 'pending');
    }

    public function test_expense_claim_with_receipt_and_resubmit_after_send_back(): void
    {
        $category = ExpenseCategory::query()->where('is_active', true)->firstOrFail();

        $created = $this->asEmployee()->post('/api/mobile/expenses', [
            'claim_date' => '2026-10-06',
            'lines' => [[
                'category_id' => $category->id, 'expense_date' => '2026-10-05', 'requested_amount' => '450.50',
                'vendor' => 'Uber', 'payment_mode' => 'upi', 'receipt' => UploadedFile::fake()->image('bill.jpg'),
            ]],
        ], ['Accept' => 'application/json'])->assertCreated();

        $claimId = $created->json('claim.id');
        $lineId = $created->json('claim.lines.0.id');
        $this->assertTrue($created->json('claim.lines.0.has_receipt'));
        $this->asEmployee()->get("/api/mobile/expense-lines/{$lineId}/receipt")->assertOk();

        $instance = ExpenseClaim::find($claimId)->approvalInstance;
        app(\App\Services\ApprovalWorkflowService::class)->act($instance, $this->manager, 'send_back', 'Add the bill number');

        $this->asEmployee()->getJson("/api/mobile/expenses/{$claimId}")->assertJsonPath('claim.can_resubmit', true);

        $this->asEmployee()->post("/api/mobile/expenses/{$claimId}/resubmit", [
            'claim_date' => '2026-10-06',
            'lines' => [[
                'category_id' => $category->id, 'expense_date' => '2026-10-05', 'requested_amount' => '450.50',
                'bill_number' => 'UB-123', 'existing_line_id' => $lineId,
            ]],
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('claim.lines.0.bill_number', 'UB-123')
            ->assertJsonPath('claim.lines.0.has_receipt', true); // receipt carried over
    }

    public function test_cannot_see_another_employees_claim(): void
    {
        $category = ExpenseCategory::query()->where('is_active', true)->firstOrFail();
        $claimId = $this->asManager()->postJson('/api/mobile/expenses', [
            'claim_date' => '2026-10-06',
            'lines' => [['category_id' => $category->id, 'expense_date' => '2026-10-05', 'requested_amount' => 100]],
        ])->json('claim.id');

        $this->asEmployee()->getJson("/api/mobile/expenses/{$claimId}")->assertNotFound();
    }

    public function test_optional_holiday_claim_and_cancel(): void
    {
        $holiday = Holiday::create(['name' => 'Dussehra (optional)', 'date' => '2026-10-20', 'type' => Holiday::TYPE_OPTIONAL, 'company_id' => $this->employee->employee->company_id]);
        OptionalHolidayLimit::create(['year' => 2026, 'max_claims' => 2]);

        $this->asEmployee()->getJson('/api/mobile/holidays?year=2026')->assertJsonPath('holidays.0.can_claim', true);
        $this->asEmployee()->postJson("/api/mobile/holidays/{$holiday->id}/claim")->assertOk();

        $list = $this->asEmployee()->getJson('/api/mobile/holidays?year=2026')->assertJsonPath('holidays.0.claimed', true);
        $this->asEmployee()->postJson('/api/mobile/optional-holiday-claims/'.$list->json('holidays.0.claim_id').'/cancel')->assertOk();
    }

    public function test_change_password(): void
    {
        $this->asEmployee()->postJson('/api/mobile/password', ['current_password' => 'wrong', 'password' => 'NewPass#2026', 'password_confirmation' => 'NewPass#2026'])
            ->assertStatus(422);

        $this->asEmployee()->postJson('/api/mobile/password', ['current_password' => 'secret123', 'password' => 'NewPass#2026', 'password_confirmation' => 'NewPass#2026'])
            ->assertOk();

        $this->assertTrue(Hash::check('NewPass#2026', $this->employee->fresh()->password));
    }

    public function test_manager_team_view(): void
    {
        Attendance::create(['employee_id' => $this->employee->employee_id, 'attendance_date' => '2026-10-07', 'first_in' => '09:31:00', 'last_out' => '09:31:00', 'status' => Attendance::STATUS_PRESENT]);

        $this->asManager()->getJson('/api/mobile/me')->assertJsonPath('user.is_manager', true);
        $this->asEmployee()->getJson('/api/mobile/me')->assertJsonPath('user.is_manager', false);

        $team = $this->asManager()->getJson('/api/mobile/team')->assertOk();
        $this->assertSame(1, $team->json('summary.total'));
        $this->assertSame(1, $team->json('summary.present'));
        $this->assertSame('EMP900', $team->json('members.0.employee_code'));
        $this->assertSame('09:31:00', $team->json('members.0.first_in'));
        $this->assertNull($team->json('members.0.last_out')); // single punch → no out time

        $memberId = $this->employee->employee_id;
        $this->asManager()->getJson("/api/mobile/team/{$memberId}/attendance?month=2026-10")->assertOk()->assertJsonCount(31, 'days');

        $this->asEmployee()->postJson('/api/mobile/wfh', ['from_date' => '2026-10-08', 'to_date' => '2026-10-08', 'reason' => 'x'])->assertCreated();
        $this->asManager()->getJson('/api/mobile/team/requests?type=wfh')->assertJsonPath('items.0.employee.employee_code', 'EMP900');
        $this->asManager()->getJson('/api/mobile/team/leave?month=2026-10')->assertOk();

        // An employee has no team, and can't look at their manager's attendance.
        $this->asEmployee()->getJson('/api/mobile/team')->assertForbidden();
        $this->asEmployee()->getJson("/api/mobile/team/{$this->manager->employee_id}/attendance")->assertNotFound();
    }

    public function test_push_notification_goes_to_the_approvers_phone(): void
    {
        // Test-only service-account key so the JWT/OAuth flow runs for real against faked Google endpoints.
        config(['services.firebase.credentials' => base_path('tests/fixtures/firebase-test-service-account.json')]);

        \Illuminate\Support\Facades\Http::fake([
            'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['name' => 'projects/vodo-hrms-test/messages/1']),
        ]);

        $this->asManager()->postJson('/api/mobile/push-token', ['token' => 'manager-fcm-token'])->assertOk();

        $this->asEmployee()->postJson('/api/mobile/wfh', ['from_date' => '2026-10-08', 'to_date' => '2026-10-08', 'reason' => 'Plumber'])->assertCreated();

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/vodo-hrms-test/messages:send')
            && $request['message']['token'] === 'manager-fcm-token'
            && $request['message']['data']['screen'] === 'approvals'
            && str_starts_with($request['message']['notification']['title'], 'Approval needed'));

        // Signing out stops pushes to that phone.
        $this->asManager()->postJson('/api/mobile/logout')->assertOk();
        $this->assertNull(\App\Models\MobileDevice::where('user_id', $this->manager->id)->first()->fcm_token);

    }

    public function test_dead_push_tokens_are_cleared(): void
    {
        // Test-only service-account key so the JWT/OAuth flow runs for real against faked Google endpoints.
        config(['services.firebase.credentials' => base_path('tests/fixtures/firebase-test-service-account.json')]);

        \Illuminate\Support\Facades\Http::fake([
            'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 't']),
            'fcm.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        $this->asEmployee()->postJson('/api/mobile/push-token', ['token' => 'stale'])->assertOk();
        $sent = app(\App\Services\FirebasePushService::class)->sendToUser($this->employee, 'Hi', 'Test');

        $this->assertSame(0, $sent);
        $this->assertNull(\App\Models\MobileDevice::where('user_id', $this->employee->id)->first()->fcm_token);

    }

    public function test_payslips_policies_and_loans_lists(): void
    {
        $this->asEmployee()->getJson('/api/mobile/payslips')->assertOk()->assertJsonPath('items', []);
        $this->asEmployee()->getJson('/api/mobile/policies')->assertOk();

        // No loan workflow configured yet → a clear message, not a 404.
        $this->asEmployee()->postJson('/api/mobile/loans', ['type' => 'salary_advance', 'requested_amount' => 5000, 'reason' => 'Medical'])
            ->assertStatus(422)->assertJsonPath('message', 'This request type has no approval workflow configured yet. Please contact HR.');

        $this->seed(\Database\Seeders\Phase6Seeder::class);
        $this->asEmployee()->postJson('/api/mobile/loans', ['type' => 'salary_advance', 'requested_amount' => 5000, 'reason' => 'Medical'])->assertCreated();
        $this->asEmployee()->getJson('/api/mobile/loans')->assertJsonPath('items.0.type_label', 'Salary Advance');
    }
}
