<?php

namespace Tests\Feature;

use App\Filament\Resources\ProbationReviewResource\Pages\CreateProbationReview;
use App\Models\ApprovalAction;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ProbationReview;
use App\Models\User;
use App\Notifications\ApprovalOutcomeNotification;
use App\Notifications\ProbationDecisionNotification;
use App\Notifications\ProbationDueNotification;
use App\Services\ApprovalWorkflowService;
use App\Services\ProbationService;
use Carbon\Carbon;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProbationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $manager;

    private User $probationer;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase2Seeder::class);
        Storage::fake('local');
        Notification::fake();
        // Probationer joined 2026-05-01 with a 180-day probation -> ends 2026-10-28.
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00'));

        $this->hr = $this->makeUser('HR001', 'HR Admin');
        $this->manager = $this->makeUser('MGR001', 'Manager');
        $this->probationer = $this->makeUser('EMP001', 'Employee', [
            'reporting_manager_id' => $this->manager->employee_id,
            'date_of_joining' => '2026-05-01',
            'probation_period_days' => 180,
            'status' => Employee::STATUS_PROBATION,
        ]);
        $this->colleague = $this->makeUser('EMP002', 'Employee', ['reporting_manager_id' => $this->manager->employee_id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $code, string $role, array $attributes = []): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create($attributes + [
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => '2024-01-01',
            'status' => Employee::STATUS_ACTIVE,
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

    private function submit(array $data = []): ProbationReview
    {
        return app(ProbationService::class)->submit($this->probationer->employee, $data + [
            'recommendation' => ProbationReview::RECOMMEND_CONFIRM,
            'rating' => 4,
            'performance_summary' => 'Consistently met targets.',
        ], $this->manager);
    }

    public function test_due_list_includes_employee_ending_within_window(): void
    {
        $service = app(ProbationService::class);

        $this->assertSame('2026-10-28', $this->probationer->employee->probationEndDate()->toDateString());
        $this->assertSame([$this->probationer->employee_id], $service->dueEmployees(30)->pluck('id')->all());
        $this->assertCount(0, $service->dueEmployees(5));
        // A manager's scope only covers their reporting line.
        $this->assertCount(0, $service->dueEmployees(30, [$this->colleague->employee_id]));
    }

    public function test_manager_recommends_confirmation_and_hr_approves(): void
    {
        $review = $this->submit();
        $workflow = app(ApprovalWorkflowService::class);

        $this->assertSame(ProbationReview::STATUS_PENDING, $review->status);
        $this->assertSame('2026-10-28', $review->effective_date->toDateString());
        $this->assertFalse($workflow->canUserActOnInstance($review->approvalInstance, $this->manager));
        $this->assertFalse($workflow->canUserActOnInstance($review->approvalInstance, $this->probationer));
        $this->assertTrue($workflow->canUserActOnInstance($review->approvalInstance, $this->hr));

        $workflow->act($review->approvalInstance, $this->hr, ApprovalAction::ACTION_APPROVE);

        $review->refresh();
        $employee = $this->probationer->employee->fresh();
        $this->assertSame(ProbationReview::STATUS_APPROVED, $review->status);
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->status);
        $this->assertSame('2026-10-28', $employee->confirmation_date->toDateString());
        $this->assertNotNull($review->letter_path);
        Storage::disk('local')->assertExists($review->letter_path);

        // The employee hears the final decision; the manager gets the approval outcome.
        Notification::assertSentTo($this->probationer, ProbationDecisionNotification::class);
        Notification::assertSentTo($this->manager, ApprovalOutcomeNotification::class);
        Notification::assertNotSentTo($this->probationer, ApprovalOutcomeNotification::class);
    }

    public function test_extension_lengthens_probation_and_keeps_status(): void
    {
        $review = $this->submit([
            'recommendation' => ProbationReview::RECOMMEND_EXTEND,
            'extension_days' => 90,
            'improvement_areas' => 'Product knowledge.',
        ]);
        $this->assertSame('2027-01-26', $review->effective_date->toDateString());

        app(ApprovalWorkflowService::class)->act($review->approvalInstance, $this->hr, ApprovalAction::ACTION_APPROVE);

        $employee = $this->probationer->employee->fresh();
        $this->assertSame(Employee::STATUS_PROBATION, $employee->status);
        $this->assertSame(270, $employee->probation_period_days);
        $this->assertSame('2027-01-26', $employee->probationEndDate()->toDateString());
        $this->assertNull($employee->confirmation_date);
        Storage::disk('local')->assertExists($review->fresh()->letter_path);
    }

    public function test_rejection_changes_nothing_and_does_not_tell_the_employee(): void
    {
        $review = $this->submit();
        app(ApprovalWorkflowService::class)->act($review->approvalInstance, $this->hr, ApprovalAction::ACTION_REJECT, 'Need more data');

        $this->assertSame(ProbationReview::STATUS_REJECTED, $review->fresh()->status);
        $this->assertSame(Employee::STATUS_PROBATION, $this->probationer->employee->fresh()->status);
        Notification::assertSentTo($this->manager, ApprovalOutcomeNotification::class);
        Notification::assertNothingSentTo($this->probationer);
    }

    public function test_validation_rules(): void
    {
        try {
            $this->submit(['recommendation' => ProbationReview::RECOMMEND_EXTEND]);
            $this->fail('Extension without days should fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('extension_days', $e->errors());
        }

        $this->submit();

        try {
            $this->submit();
            $this->fail('Second pending review should fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('employee_id', $e->errors());
        }

        $this->expectException(ValidationException::class);
        app(ProbationService::class)->submit($this->colleague->employee, [
            'recommendation' => ProbationReview::RECOMMEND_CONFIRM, 'performance_summary' => 'x',
        ], $this->manager);
    }

    public function test_sent_back_review_can_be_resubmitted(): void
    {
        $first = $this->submit();
        app(ApprovalWorkflowService::class)->act($first->approvalInstance, $this->hr, ApprovalAction::ACTION_SEND_BACK, 'Add a rating rationale');
        $this->assertSame(ProbationReview::STATUS_SENT_BACK, $first->fresh()->status);

        $second = $this->submit(['performance_summary' => 'Met targets; strong customer feedback.']);

        $this->assertSame(ProbationReview::STATUS_REJECTED, $first->fresh()->status);
        $this->assertSame(ProbationReview::STATUS_PENDING, $second->status);
    }

    public function test_reminders_go_to_manager_and_hr_on_milestone_days_only(): void
    {
        // 2026-10-21 is 7 days before the end date.
        $this->artisan('hr:send-probation-reminders')->assertSuccessful();
        Notification::assertSentTo($this->manager, ProbationDueNotification::class);
        Notification::assertSentTo($this->hr, ProbationDueNotification::class);
        Notification::assertNotSentTo($this->probationer, ProbationDueNotification::class);

        // 6 days before: not a milestone.
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-22 08:15'));
        $this->artisan('hr:send-probation-reminders')->assertSuccessful();
        Notification::assertNothingSent();

        // On the day, but a review is already pending: no reminder.
        Carbon::setTestNow(Carbon::parse('2026-10-28 08:15'));
        $this->submit();
        Notification::fake();
        $this->artisan('hr:send-probation-reminders')->assertSuccessful();
        Notification::assertNotSentTo($this->manager, ProbationDueNotification::class);
    }

    public function test_pages_and_access(): void
    {
        // The panel's session check logs out when the user changes mid-session, so start fresh per user.
        $as = function (User $user) {
            $this->flushSession();

            return $this->actingAs($user);
        };

        $as($this->hr)->get('/admin/probation-due')->assertOk()->assertSee('EMP001');
        $as($this->hr)->get('/admin/probation-reviews')->assertOk();
        $as($this->manager)->get('/admin/probation-due')->assertOk()->assertSee('EMP001');
        $as($this->manager)->get('/admin/probation-reviews/create?employee='.$this->probationer->employee_id)->assertOk();

        // A regular employee (no reports, no HR permission) has no access.
        $as($this->probationer)->get('/admin/probation-due')->assertForbidden();
        $as($this->probationer)->get('/admin/probation-reviews')->assertForbidden();
    }

    public function test_manager_submits_through_the_form(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(CreateProbationReview::class)
            ->fillForm([
                'employee_id' => $this->probationer->employee_id,
                'recommendation' => ProbationReview::RECOMMEND_CONFIRM,
                'rating' => 4,
                'performance_summary' => 'Good work.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $review = ProbationReview::sole();
        $this->assertSame($this->manager->id, $review->submitted_by);
        $this->assertNotNull($review->approval_instance_id);
    }

    public function test_letter_download_access(): void
    {
        $review = $this->submit();
        app(ApprovalWorkflowService::class)->act($review->approvalInstance, $this->hr, ApprovalAction::ACTION_APPROVE);

        $url = route('probation-letters.download', $review);
        $this->actingAs($this->probationer)->get($url)->assertOk();
        $this->actingAs($this->manager)->get($url)->assertOk();
        $this->actingAs($this->colleague)->get($url)->assertForbidden();
    }
}
