<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTask;
use App\Models\OnboardingTaskTemplate;
use App\Models\User;
use App\Notifications\OnboardingTasksAssignedNotification;
use App\Services\OnboardingService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OnboardingTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00')); // a Friday

        $this->hr = $this->makeUser('HR001', 'HR Admin');
        $this->manager = $this->makeUser('MGR001', 'Manager');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $code, string $role, array $attributes = []): User
    {
        $employee = $this->makeEmployee($code, $attributes + ['date_of_joining' => '2024-01-01']);
        $user = User::create([
            'employee_id' => $employee->id, 'employee_code' => $code, 'name' => $code,
            'email' => $employee->official_email, 'password' => bcrypt('secret123'), 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function makeEmployee(string $code, array $attributes = []): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        return Employee::create($attributes + [
            'employee_code' => $code, 'first_name' => $code, 'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id, 'status' => Employee::STATUS_PROBATION, 'weekly_off' => ['saturday', 'sunday'],
        ]);
    }

    private function newJoiner(): Employee
    {
        return $this->makeEmployee('NEW001', ['date_of_joining' => '2026-11-20', 'reporting_manager_id' => $this->manager->employee_id]);
    }

    public function test_new_joiner_gets_tasks_with_resolved_owners(): void
    {
        $joiner = $this->newJoiner();
        $tasks = $joiner->onboardingTasks()->get();
        $templates = OnboardingTaskTemplate::active()->count();

        $this->assertGreaterThan(0, $templates);
        $this->assertCount($templates, $tasks);
        $this->assertTrue(OnboardingChecklist::where('employee_id', $joiner->id)->exists());

        $managerTask = $tasks->firstWhere('assignee_type', OnboardingTaskTemplate::ASSIGNEE_MANAGER);
        $this->assertSame($this->manager->id, $managerTask->assigned_user_id);

        $hrTask = $tasks->firstWhere('assignee_type', OnboardingTaskTemplate::ASSIGNEE_HR);
        $this->assertNull($hrTask->assigned_user_id);
        $this->assertNotNull($hrTask->role_id); // HR Admin role (no HR manager set)

        // Due dates follow the joining date offsets (first template: 7 days before).
        $this->assertSame('2026-11-13', $tasks->sortBy('sequence')->first()->due_date->toDateString());

        Notification::assertSentTo($this->manager, OnboardingTasksAssignedNotification::class);
        Notification::assertSentTo($this->hr, OnboardingTasksAssignedNotification::class);
    }

    public function test_long_standing_employees_do_not_get_tasks(): void
    {
        $old = $this->makeEmployee('OLD001', ['date_of_joining' => '2025-01-01']);
        $this->assertSame(0, $old->onboardingTasks()->count());

        // HR can still start them by hand, once.
        app(OnboardingService::class)->generateTasks($old);
        $this->assertGreaterThan(0, $old->onboardingTasks()->count());
        $this->expectException(ValidationException::class);
        app(OnboardingService::class)->generateTasks($old->fresh());
    }

    public function test_owners_complete_tasks_and_joiner_sees_their_own(): void
    {
        $joiner = $this->newJoiner();
        $joinerUser = User::create([
            'employee_id' => $joiner->id, 'employee_code' => 'NEW001', 'name' => 'NEW001',
            'email' => $joiner->official_email, 'password' => bcrypt('secret123'), 'is_active' => true,
        ]);
        $joinerUser->assignRole('Employee');
        $service = app(OnboardingService::class);

        $managerTask = $joiner->onboardingTasks()->where('assignee_type', 'reporting_manager')->first();
        $ownTask = $joiner->onboardingTasks()->where('assignee_type', 'employee')->first();

        // The joiner's tasks were created before they had a login, yet they own them.
        $this->assertTrue($service->canAct($ownTask, $joinerUser));
        $this->assertFalse($service->canAct($managerTask, $joinerUser));
        $this->assertSame(
            $joiner->onboardingTasks()->where('assignee_type', 'employee')->count(),
            OnboardingTask::query()->assignedTo($joinerUser)->count(),
        );

        $service->complete($managerTask, $this->manager, 'Done on day one');
        $this->assertSame(OnboardingTask::STATUS_DONE, $managerTask->fresh()->status);
        $this->assertSame($this->manager->id, $managerTask->fresh()->completed_by);

        $this->expectException(ValidationException::class);
        $service->skip($ownTask, $this->hr, '');
    }

    public function test_overdue_reminder(): void
    {
        $joiner = $this->newJoiner();
        Notification::fake();

        // 2026-11-21: the joining-day tasks (due 11-20) went overdue yesterday.
        Carbon::setTestNow(Carbon::parse('2026-11-21 08:30'));
        $this->artisan('hr:send-onboarding-reminders')->assertSuccessful();
        Notification::assertSentTo($this->manager, OnboardingTasksAssignedNotification::class);

        // Once done, no more reminders for it.
        $joiner->onboardingTasks()->update(['status' => OnboardingTask::STATUS_DONE]);
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-23 08:30')); // Monday: all overdue
        $this->artisan('hr:send-onboarding-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_pages_and_scoping(): void
    {
        $this->newJoiner();
        $as = function (User $user) {
            $this->flushSession();

            return $this->actingAs($user);
        };

        $as($this->hr)->get('/admin/onboarding-tasks')->assertOk()->assertSee('NEW001');
        $as($this->hr)->get('/admin/onboarding-task-templates')->assertOk();
        $as($this->manager)->get('/admin/onboarding-tasks')->assertOk()->assertSee('Welcome and team introduction')->assertDontSee('Collect joining documents');
        $as($this->manager)->get('/admin/onboarding-task-templates')->assertForbidden();

        $nobody = $this->makeUser('EMP009', 'Employee');
        $as($nobody)->get('/admin/onboarding-tasks')->assertForbidden();
    }
}
