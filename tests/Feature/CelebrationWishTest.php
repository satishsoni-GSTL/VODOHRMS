<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\MobileDevice;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\BirthdayNotification;
use App\Notifications\Channels\PushChannel;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CelebrationWishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-10-08 09:00');
        config(['services.firebase.credentials' => base_path('tests/fixtures/firebase-test-service-account.json')]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/x/messages/1']),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employeeWithPhone(string $code, array $attrs): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        $e = Employee::create($attrs + [
            'employee_code' => $code, 'first_name' => ucfirst(strtolower($code)), 'official_email' => strtolower($code).'@x.test',
            'company_id' => $company->id, 'date_of_joining' => '2025-03-01', 'status' => Employee::STATUS_ACTIVE,
        ]);
        $u = User::create(['employee_id' => $e->id, 'employee_code' => $code, 'name' => $code, 'email' => $e->official_email, 'password' => bcrypt('x'), 'is_active' => true]);
        $u->assignRole('Employee');
        MobileDevice::create(['user_id' => $u->id, 'token_hash' => hash('sha256', $code), 'fcm_token' => "fcm-{$code}"]);

        return $u;
    }

    private function pushesTo(string $fcmToken): \Illuminate\Support\Collection
    {
        return Http::recorded(fn ($r) => str_contains($r->url(), 'messages:send') && $r['message']['token'] === $fcmToken)
            ->map(fn ($pair) => $pair[0]['message']['notification']);
    }

    public function test_celebrant_gets_one_personal_wish_at_nine_and_colleagues_do_not(): void
    {
        $this->employeeWithPhone('PRIYA', ['dob' => '1994-10-08']);                                   // birthday today
        $this->employeeWithPhone('RAVI', ['date_of_joining' => '2023-10-08']);                         // 3-year anniversary
        $this->employeeWithPhone('ASHA', ['dob' => '1990-01-01']);                                     // nothing today
        $this->employeeWithPhone('LEFT', ['dob' => '1994-10-08', 'status' => Employee::STATUS_EXITED]); // left the company

        $this->artisan('hr:send-celebration-wishes')->assertSuccessful();
        $this->artisan('hr:send-celebration-wishes')->assertSuccessful(); // re-run: no duplicates

        $priya = $this->pushesTo('fcm-PRIYA');
        $this->assertCount(1, $priya);
        $this->assertSame('Happy Birthday, Priya! 🎂', $priya->first()['title']);

        $ravi = $this->pushesTo('fcm-RAVI');
        $this->assertCount(1, $ravi);
        $this->assertSame('Happy 3-Year Work Anniversary, Ravi! 🎉', $ravi->first()['title']);

        $this->assertCount(0, $this->pushesTo('fcm-ASHA'));
        $this->assertCount(0, $this->pushesTo('fcm-LEFT'));
    }

    public function test_hr_can_change_the_wording(): void
    {
        NotificationTemplate::create(['key' => 'birthday_wish', 'subject' => 'Many happy returns, {first_name}!', 'body' => 'From all of us at GlobalSpace.']);
        $this->employeeWithPhone('MEERA', ['dob' => '1992-10-08']);

        $this->artisan('hr:send-celebration-wishes');

        $push = $this->pushesTo('fcm-MEERA')->first();
        $this->assertSame('Many happy returns, Meera!', $push['title']);
        $this->assertSame('From all of us at GlobalSpace.', $push['body']);
    }

    public function test_eight_am_reminder_no_longer_pushes_the_celebrant_but_still_pushes_colleagues(): void
    {
        $celebrant = $this->employeeWithPhone('KARAN', ['dob' => '1991-10-08']);
        $colleague = $this->employeeWithPhone('SNEHA', []);
        $notification = new BirthdayNotification($celebrant->employee);

        $this->assertNotContains(PushChannel::class, $notification->via($celebrant));
        $this->assertContains(PushChannel::class, $notification->via($colleague));
    }

    public function test_app_home_shows_the_wish_card_on_the_day(): void
    {
        $this->employeeWithPhone('ANU', ['dob' => '1995-10-08', 'date_of_joining' => '2024-10-08']);
        $token = $this->postJson('/api/mobile/login', ['login' => 'ANU', 'password' => 'x'])->json('token');

        $this->withToken($token)->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonCount(2, 'my_wishes')
            ->assertJsonPath('my_wishes.0.type', 'birthday')
            ->assertJsonPath('my_wishes.1.type', 'anniversary')
            ->assertJsonPath('my_wishes.1.years', 2);
    }
}
