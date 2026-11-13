<?php

namespace Tests\Feature;

use App\Models\ApprovalAction;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\ExpenseClaimService;
use App\Services\TravelService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TravelRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00'));

        $this->finance = $this->makeUser('FIN001', 'Finance Admin');
        $this->manager = $this->makeUser('MGR001', 'Manager');
        $this->employee = $this->makeUser('EMP001', 'Employee', $this->manager->employee_id);
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
            'employee_code' => $code, 'first_name' => $code, 'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id, 'date_of_joining' => '2024-01-01', 'status' => Employee::STATUS_ACTIVE,
            'reporting_manager_id' => $managerId, 'weekly_off' => ['saturday', 'sunday'],
        ]);
        $user = User::create([
            'employee_id' => $employee->id, 'employee_code' => $code, 'name' => $code,
            'email' => $employee->official_email, 'password' => bcrypt('secret123'), 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function trip(array $data = []): TravelRequest
    {
        return app(TravelService::class)->request($this->employee->employee, $data + [
            'purpose' => 'Client visit', 'travel_mode' => 'air',
            'from_location' => 'Mumbai', 'to_location' => 'Delhi',
            'departure_date' => '2026-11-20', 'return_date' => '2026-11-22',
            'estimated_cost' => 30000, 'advance_requested' => 0,
        ]);
    }

    private function approve(TravelRequest $trip, User $by): void
    {
        app(ApprovalWorkflowService::class)->act($trip->fresh()->approvalInstance, $by, ApprovalAction::ACTION_APPROVE);
    }

    public function test_trip_without_advance_needs_only_the_manager(): void
    {
        $trip = $this->trip();
        $this->assertStringStartsWith('TR-202611-', $trip->request_number);
        $this->assertSame(3, $trip->days);

        $this->approve($trip, $this->manager);

        $this->assertSame(TravelRequest::STATUS_APPROVED, $trip->fresh()->status);
    }

    public function test_trip_with_advance_also_needs_finance_then_advance_is_netted_off_the_claim(): void
    {
        $trip = $this->trip(['advance_requested' => 10000]);
        $workflow = app(ApprovalWorkflowService::class);

        $this->assertFalse($workflow->canUserActOnInstance($trip->approvalInstance, $this->finance));
        $this->approve($trip, $this->manager);
        $this->assertSame(TravelRequest::STATUS_PENDING, $trip->fresh()->status);
        $this->approve($trip, $this->finance);
        $this->assertSame(TravelRequest::STATUS_APPROVED, $trip->fresh()->status);

        $service = app(TravelService::class);
        $service->recordAdvancePayment($trip->fresh(), 10000, '2026-11-15', 'UTR1', $this->finance);
        $this->assertEquals(10000, $trip->fresh()->outstandingAdvance());

        try {
            $service->recordAdvancePayment($trip->fresh(), 10000, '2026-11-15', 'UTR2', $this->finance);
            $this->fail('Advance should not be payable twice');
        } catch (ValidationException) {
        }

        // An approved claim of 7,500 for this trip absorbs 7,500 of the advance; nothing is paid out.
        $claim = ExpenseClaim::create([
            'claim_number' => 'EXP-1', 'employee_id' => $this->employee->employee_id, 'claim_date' => '2026-11-23',
            'status' => ExpenseClaim::STATUS_APPROVED, 'total_requested_amount' => 7500, 'total_approved_amount' => 7500,
        ]);
        $service->linkClaim($claim, $trip->id);
        $this->assertEquals(7500, $service->adjustmentFor($claim->fresh()));

        app(ExpenseClaimService::class)->recordPayment($claim->fresh(), 0, '2026-11-25', null, $this->finance->id);
        $this->assertEquals(7500, $claim->fresh()->advance_adjusted);
        $this->assertEquals(2500, $trip->fresh()->outstandingAdvance());

        // Closing with unadjusted advance needs a note on how it was recovered.
        try {
            $service->close($trip->fresh(), null);
            $this->fail('Close without remarks should fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('closure_remarks', $e->errors());
        }
        $service->close($trip->fresh(), 'Balance 2,500 returned by employee.');
        $this->assertSame(TravelRequest::STATUS_CLOSED, $trip->fresh()->status);
    }

    public function test_validation(): void
    {
        $cases = [
            ['departure_date' => '2026-11-10', 'return_date' => '2026-11-12'],   // in the past
            ['return_date' => '2026-11-19'],                                       // return before departure
            ['estimated_cost' => 5000, 'advance_requested' => 6000],                // advance > estimate
        ];

        foreach ($cases as $case) {
            try {
                $this->trip($case);
                $this->fail('Expected validation error for '.json_encode($case));
            } catch (ValidationException) {
            }
        }

        $this->trip();
        $this->expectException(ValidationException::class);
        $this->trip(['departure_date' => '2026-11-21', 'return_date' => '2026-11-25']); // overlaps
    }

    public function test_only_own_approved_trip_can_be_linked(): void
    {
        $trip = $this->trip();
        $claim = ExpenseClaim::create([
            'claim_number' => 'EXP-2', 'employee_id' => $this->employee->employee_id, 'claim_date' => '2026-11-23',
            'status' => ExpenseClaim::STATUS_SUBMITTED, 'total_requested_amount' => 100,
        ]);

        $this->expectException(ValidationException::class);
        app(TravelService::class)->linkClaim($claim, $trip->id); // still pending
    }

    public function test_cancel_and_mobile_api(): void
    {
        $token = $this->postJson('/api/mobile/login', ['login' => 'EMP001', 'password' => 'secret123'])->json('token');

        $id = $this->withToken($token)->postJson('/api/mobile/travel', [
            'purpose' => 'Plant audit', 'travel_mode' => 'train', 'from_location' => 'Pune', 'to_location' => 'Nashik',
            'departure_date' => '2026-11-18', 'return_date' => '2026-11-18', 'advance_requested' => 0,
        ])->assertCreated()->json('id');

        $this->withToken($token)->getJson('/api/mobile/travel')
            ->assertOk()
            ->assertJsonPath('items.0.status', TravelRequest::STATUS_PENDING)
            ->assertJsonPath('items.0.can_cancel', true);

        $this->withToken($token)->postJson("/api/mobile/travel/{$id}/cancel")->assertOk();
        $this->assertSame(TravelRequest::STATUS_CANCELLED, TravelRequest::find($id)->status);
    }

    public function test_pages_render(): void
    {
        $trip = $this->trip();
        $this->actingAs($this->employee)->get('/admin/travel-requests')->assertOk()->assertSee($trip->request_number);
        $this->actingAs($this->employee)->get('/admin/travel-requests/create')->assertOk();
        $this->actingAs($this->employee)->get("/admin/travel-requests/{$trip->id}")->assertOk();
    }
}
