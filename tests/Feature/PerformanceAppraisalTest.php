<?php

namespace Tests\Feature;

use App\Filament\Resources\AppraisalResource\Pages\EditAppraisal;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\PerformanceNotification;
use App\Services\AppraisalService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceAppraisalTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $manager;

    private User $employee;

    private User $colleague;

    private AppraisalCycle $cycle;

    private AppraisalService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00'));

        $this->hr = $this->makeUser('HR001', 'HR Admin');
        $this->manager = $this->makeUser('MGR001', 'Manager');
        $this->employee = $this->makeUser('EMP001', 'Employee', $this->manager->employee_id);
        $this->colleague = $this->makeUser('EMP002', 'Employee', $this->manager->employee_id);

        $this->cycle = AppraisalCycle::create([
            'name' => 'FY 2026-27', 'period_start' => '2026-04-01', 'period_end' => '2027-03-31', 'min_goals' => 2,
        ]);
        $this->svc = app(AppraisalService::class);
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

    private function appraisal(): Appraisal
    {
        return Appraisal::where('employee_id', $this->employee->employee_id)->firstOrFail();
    }

    private function goals(array $weights): array
    {
        return ['goals' => collect($weights)->map(fn ($w, $i) => ['title' => "Goal {$i}", 'target' => 'Target', 'weightage' => $w])->all()];
    }

    private function ratingsFor(Appraisal $a, string $who, array $ratings): array
    {
        return ['goals' => $a->goals()->get()->values()->map(fn ($g, $i) => ['id' => $g->id, "{$who}_rating" => $ratings[$i], "{$who}_comments" => 'ok'])->all()];
    }

    public function test_full_cycle(): void
    {
        $this->assertSame(4, $this->svc->launch($this->cycle));
        $a = $this->appraisal();
        $this->assertSame($this->manager->employee_id, $a->manager_id);
        $this->assertSame(Appraisal::STATUS_GOALS_DRAFT, $a->status);
        Notification::assertSentTo($this->employee, PerformanceNotification::class);

        // Goals: weightage must total 100.
        $this->svc->save($a, $this->goals([60, 30]), $this->employee);
        try {
            $this->svc->submitGoals($a->fresh(), $this->employee);
            $this->fail('90% should not submit');
        } catch (ValidationException) {
        }
        $this->svc->save($a->fresh(), ['goals' => $a->goals()->get()->map(fn ($g) => ['id' => $g->id, 'title' => $g->title, 'weightage' => $g->weightage === 30 ? 40 : 60])->all()], $this->employee);
        $this->svc->submitGoals($a->fresh(), $this->employee);
        $this->assertSame(Appraisal::STATUS_GOALS_SUBMITTED, $a->fresh()->status);

        // The employee can't approve their own goals; a colleague can't either.
        $this->assertFalse($this->svc->canApproveGoals($a->fresh(), $this->employee));
        $this->assertFalse($this->svc->canApproveGoals($a->fresh(), $this->colleague));

        // Return, fix, resubmit, approve.
        $this->svc->returnGoals($a->fresh(), $this->manager, 'Make goal 1 measurable');
        $this->assertSame(Appraisal::STATUS_GOALS_DRAFT, $a->fresh()->status);
        $this->svc->submitGoals($a->fresh(), $this->employee);
        $this->svc->approveGoals($a->fresh(), $this->manager);
        $this->assertSame(Appraisal::STATUS_GOALS_APPROVED, $a->fresh()->status);

        // Self review only once HR opens the review phase.
        $this->assertFalse($this->svc->canSelfReview($a->fresh(), $this->employee));
        $this->svc->startReview($this->cycle->fresh());
        $a = $a->fresh();

        // Approved goals are locked: a save in the self-review stage can't change them.
        $this->svc->save($a, ['goals' => $a->goals()->get()->map(fn ($g) => ['id' => $g->id, 'title' => 'HACKED', 'weightage' => 100, 'self_rating' => 4])->all()], $this->employee);
        $this->assertSame(0, $a->goals()->where('title', 'HACKED')->count());

        $this->svc->save($a, $this->ratingsFor($a, 'self', [4, 3]) + ['self_overall_comments' => 'Good year'], $this->employee);
        $this->svc->submitSelfReview($a->fresh(), $this->employee);
        $this->assertEquals(3.6, $a->fresh()->self_score); // 4×60% + 3×40%

        // The employee can't write manager ratings.
        $this->svc->save($a->fresh(), $this->ratingsFor($a, 'manager', [5, 5]), $this->employee);
        $this->assertSame(0, $a->goals()->whereNotNull('manager_rating')->count());

        $this->svc->save($a->fresh(), $this->ratingsFor($a, 'manager', [3, 5]), $this->manager);
        $this->svc->submitManagerReview($a->fresh(), $this->manager);
        $a = $a->fresh();
        $this->assertSame(Appraisal::STATUS_MANAGER_SUBMITTED, $a->status);
        $this->assertEquals(3.8, $a->manager_score); // 3×60% + 5×40%
        $this->assertEquals(3.8, $a->final_rating);

        // HR calibration, then close.
        $this->svc->calibrate($a, $this->hr, 3.5, 'Normalised to department curve');
        $this->assertSame(1, $this->svc->closeCycle($this->cycle->fresh()));
        $this->assertSame(Appraisal::STATUS_CLOSED, $a->fresh()->status);
        $this->assertEquals(3.5, $a->fresh()->final_rating);
    }

    public function test_cycle_order_is_enforced(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->startReview($this->cycle);
    }

    public function test_visibility_and_pages(): void
    {
        $this->svc->launch($this->cycle);
        $a = $this->appraisal();
        $as = function (User $user) {
            $this->flushSession();

            return $this->actingAs($user);
        };

        $as($this->employee)->get('/admin/appraisals')->assertOk()->assertSee('EMP001')->assertDontSee('EMP002');
        $as($this->manager)->get('/admin/appraisals')->assertOk()->assertSee('EMP001')->assertSee('EMP002');
        $as($this->hr)->get('/admin/appraisal-cycles')->assertOk()->assertSee('FY 2026-27');
        $as($this->employee)->get('/admin/appraisal-cycles')->assertForbidden();

        $as($this->employee)->get("/admin/appraisals/{$a->id}/edit")->assertOk();
        $colleagueAppraisal = Appraisal::where('employee_id', $this->colleague->employee_id)->first();
        $as($this->employee)->get("/admin/appraisals/{$colleagueAppraisal->id}/edit")->assertNotFound();
    }

    public function test_review_stage_pages_hide_manager_ratings_until_submitted(): void
    {
        $this->svc->launch($this->cycle);
        $a = $this->appraisal();
        $this->svc->save($a, $this->goals([50, 50]), $this->employee);
        $this->svc->submitGoals($a->fresh(), $this->employee);
        $this->svc->approveGoals($a->fresh(), $this->manager);
        $this->svc->startReview($this->cycle->fresh());
        $this->svc->save($a->fresh(), $this->ratingsFor($a, 'self', [4, 4]), $this->employee);
        $this->svc->submitSelfReview($a->fresh(), $this->employee);
        // Manager drafts comments but has not submitted yet.
        $this->svc->save($a->fresh(), ['goals' => $a->goals()->get()->map(fn ($g) => ['id' => $g->id, 'manager_rating' => 3, 'manager_comments' => 'SECRET-DRAFT'])->all()], $this->manager);

        $this->actingAs($this->manager);
        Livewire::test(EditAppraisal::class, ['record' => $a->getRouteKey()])
            ->assertActionVisible('submitManager')
            ->assertSee('Manager rating');

        $this->flushSession();
        $this->actingAs($this->employee);
        Livewire::test(EditAppraisal::class, ['record' => $a->getRouteKey()])
            ->assertActionHidden('submitManager')
            ->assertDontSee('SECRET-DRAFT');
    }

    public function test_employee_sets_and_submits_goals_through_the_page(): void
    {
        $this->svc->launch($this->cycle);
        $a = $this->appraisal();
        $this->actingAs($this->employee);

        Livewire::test(EditAppraisal::class, ['record' => $a->getRouteKey()])
            ->set('data.goals', [
                'a' => ['id' => null, 'title' => 'Grow sales 15%', 'target' => '₹2 Cr', 'weightage' => 70, 'description' => null],
                'b' => ['id' => null, 'title' => 'Launch 2 products', 'target' => '2', 'weightage' => 30, 'description' => null],
            ])
            ->callAction('submitGoals')
            ->assertHasNoErrors();

        $a = $a->fresh();
        $this->assertSame(Appraisal::STATUS_GOALS_SUBMITTED, $a->status);
        $this->assertSame(['Grow sales 15%', 'Launch 2 products'], $a->goals()->pluck('title')->all());
        Notification::assertSentTo($this->manager, PerformanceNotification::class);
    }
}
