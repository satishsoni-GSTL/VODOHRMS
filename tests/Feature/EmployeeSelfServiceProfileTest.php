<?php

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\PrejoiningInvite;
use App\Models\User;
use App\Notifications\PerformanceNotification;
use App\Notifications\PrejoiningInviteNotification;
use App\Notifications\PrejoiningOtpNotification;
use App\Services\AppraisalService;
use App\Services\EmployeeProfileService;
use App\Services\PrejoiningService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeSelfServiceProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $employee;

    private EmployeeProfileService $profile;

    private const FULL = [
        'first_name' => 'Asha', 'last_name' => 'Rao', 'dob' => '1995-04-12', 'gender' => 'female',
        'marital_status' => 'single', 'blood_group' => 'O+',
        'personal_mobile' => '9876543210', 'personal_email' => 'asha@example.com',
        'current_address' => ['line1' => '12 MG Road', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001'],
        'permanent_address' => ['line1' => '4 Lake View', 'city' => 'Nashik', 'state' => 'Maharashtra', 'pincode' => '422001'],
        'emergency_contact_name' => 'Ravi Rao', 'emergency_contact_relation' => 'Father', 'emergency_contact_phone' => '9123456780',
        'pan' => 'abcde1234f', 'aadhaar' => '1234 5678 9012',
        'bank' => ['account_holder_name' => 'Asha Rao', 'bank_name' => 'HDFC Bank', 'account_number' => '50100012345678', 'ifsc' => 'hdfc0001234'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00'));

        $this->hr = $this->makeUser('HR001', 'HR Admin');
        $this->employee = $this->makeUser('EMP001', 'Employee');
        $this->profile = app(EmployeeProfileService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEmployee(string $code, array $attributes = []): Employee
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        return Employee::create($attributes + [
            'employee_code' => $code, 'first_name' => $code, 'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id, 'date_of_joining' => '2024-01-01', 'status' => Employee::STATUS_ACTIVE,
            'weekly_off' => ['saturday', 'sunday'],
        ]);
    }

    private function makeUser(string $code, string $role): User
    {
        $employee = $this->makeEmployee($code);
        $user = User::create([
            'employee_id' => $employee->id, 'employee_code' => $code, 'name' => $code,
            'email' => $employee->official_email, 'password' => bcrypt('secret123'), 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function uploadAllDocuments(Employee $employee, string $source = 'self'): void
    {
        foreach (array_keys(EmployeeDocument::REQUIRED_TYPES) as $type) {
            $this->profile->addDocument($employee, $type, UploadedFile::fake()->create("{$type}.pdf", 50, 'application/pdf'), $source);
        }
    }

    // ---- Completion & self-service rules ------------------------------------------------

    public function test_completion_goes_from_partial_to_full(): void
    {
        $employee = $this->employee->employee;
        $before = $this->profile->completion($employee);
        $this->assertLessThan(10, $before['percent']);
        $this->assertSame(21, $before['total']);

        $this->profile->update($employee, self::FULL, EmployeeProfileService::MODE_SELF);
        $this->uploadAllDocuments($employee->fresh());

        $after = $this->profile->completion($employee->fresh());
        $this->assertSame(100, $after['percent']);
        $statutory = $employee->fresh()->statutoryDetail;
        $this->assertSame('ABCDE1234F', $statutory->pan);   // normalised
        $this->assertSame('123456789012', $statutory->aadhaar);
        $this->assertSame('HDFC0001234', $employee->fresh()->bankDetails->first()->ifsc);
    }

    public function test_self_service_fills_but_never_overwrites_identity_statutory_or_bank(): void
    {
        $employee = $this->employee->employee;
        $this->profile->update($employee, self::FULL, EmployeeProfileService::MODE_SELF);

        $this->profile->update($employee->fresh(), [
            'last_name' => 'Changed', 'pan' => 'ZZZZZ9999Z',
            'bank' => ['account_number' => '99999999', 'ifsc' => 'ICIC0000001'],
            'personal_mobile' => '9000000001',               // contact may change
            'current_address' => ['city' => 'Mumbai'],       // partial address update keeps the rest
        ], EmployeeProfileService::MODE_SELF);

        $fresh = $employee->fresh();
        $this->assertSame('Rao', $fresh->last_name);
        $this->assertSame('ABCDE1234F', $fresh->statutoryDetail->pan);
        $this->assertSame('50100012345678', $fresh->bankDetails->first()->account_number);
        $this->assertSame('9000000001', $fresh->personal_mobile);
        $this->assertSame('Mumbai', $fresh->current_address['city']);
        $this->assertSame('12 MG Road', $fresh->current_address['line1']);

        // Blank values never wipe data.
        $this->profile->update($fresh, ['personal_mobile' => ''], EmployeeProfileService::MODE_SELF);
        $this->assertSame('9000000001', $employee->fresh()->personal_mobile);
    }

    public function test_formats_are_validated(): void
    {
        foreach ([['pan' => 'ABC123'], ['aadhaar' => '12345'], ['bank' => ['ifsc' => 'BAD']], ['personal_mobile' => '12345'], ['current_address' => ['pincode' => '12']]] as $bad) {
            try {
                $this->profile->update($this->employee->employee, $bad, EmployeeProfileService::MODE_SELF);
                $this->fail('Should reject '.json_encode($bad));
            } catch (ValidationException) {
            }
        }
        $this->assertNull($this->employee->employee->fresh()->statutoryDetail);
    }

    // ---- My Profile, dashboard, documents ------------------------------------------------

    public function test_my_profile_page_and_dashboard_widget(): void
    {
        $this->actingAs($this->employee);

        $this->get('/admin')->assertOk()->assertSee('Complete your profile')->assertSee('Fill missing details');
        $this->get('/admin/my-profile')->assertOk()->assertSee('Profile')->assertSee('Documents');

        Livewire::test(MyProfile::class)
            ->fillForm([
                'last_name' => 'Rao', 'dob' => '1995-04-12', 'gender' => 'female', 'personal_mobile' => '9876543210',
                'pan' => 'ABCDE1234F',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $employee = $this->employee->employee->fresh();
        $this->assertSame('Rao', $employee->last_name);
        $this->assertSame('ABCDE1234F', $employee->statutoryDetail->pan);

        // Bad PAN shows an error, saves nothing.
        Livewire::test(MyProfile::class)->fillForm(['aadhaar' => '12'])->call('save');
        $this->assertNull($employee->fresh()->statutoryDetail->aadhaar);
    }

    public function test_document_access_and_removal(): void
    {
        $employee = $this->employee->employee;
        $doc = $this->profile->addDocument($employee, 'pan_card', UploadedFile::fake()->create('pan.pdf', 20, 'application/pdf'), 'self', $this->employee);
        $colleague = $this->makeUser('EMP002', 'Employee');
        $url = route('employee-documents.download', $doc);

        $this->actingAs($this->employee)->get($url)->assertOk();
        $this->actingAs($this->hr)->get($url)->assertOk();
        $this->actingAs($colleague)->get($url)->assertForbidden();

        $doc->update(['is_verified' => true]);
        $this->expectException(ValidationException::class);
        $this->profile->deleteDocument($doc->fresh());
    }

    public function test_hr_sees_documents_tab_on_employee(): void
    {
        $this->profile->addDocument($this->employee->employee, 'pan_card', UploadedFile::fake()->create('pan.pdf', 20, 'application/pdf'), 'self');

        $this->actingAs($this->hr)->get("/admin/employees/{$this->employee->employee_id}/edit")->assertOk()->assertSee('Documents');
    }

    // ---- Pre-joining portal -------------------------------------------------------------

    public function test_prejoining_portal_end_to_end(): void
    {
        $hire = $this->makeEmployee('NEW001', ['date_of_joining' => '2026-11-25', 'status' => Employee::STATUS_PROBATION, 'official_email' => null]);
        $service = app(PrejoiningService::class);

        $token = null;
        $invite = $service->invite($hire, 'New.Hire@Example.com', $this->hr);
        Notification::assertSentTo($invite, PrejoiningInviteNotification::class, function ($n) use ($invite, &$token) {
            $mail = $n->toMail($invite);
            $token = basename($mail->actionUrl);

            return str_contains($mail->actionUrl, '/joining/');
        });
        $this->assertSame('new.hire@example.com', $invite->email);
        $this->assertSame('new.hire@example.com', $hire->fresh()->personal_email);
        $url = "/joining/{$token}";

        // Not verified yet: only the code screen.
        $this->get($url)->assertOk()->assertSee('Email me a code')->assertDontSee('Statutory IDs');
        $this->post("{$url}", ['first_name' => 'X'])->assertForbidden();

        $this->post("{$url}/send-code")->assertRedirect();
        Notification::assertSentTo($invite, PrejoiningOtpNotification::class);
        $invite->refresh()->forceFill(['otp_hash' => Hash::make('123456')])->save();

        $this->post("{$url}/verify", ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post("{$url}/verify", ['code' => '123456'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Statutory IDs')->assertSee('Submit to HR');

        // Submitting with details missing is refused.
        $this->post($url, ['action' => 'submit'])->assertSessionHasErrors('submit');

        // Fill everything + upload one document, then submit.
        $this->post($url, self::FULL + [
            'action' => 'submit',
            'documents' => ['pan_card' => UploadedFile::fake()->create('pan.pdf', 30, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $invite->refresh();
        $hire = $hire->fresh();
        $this->assertSame(PrejoiningInvite::STATUS_SUBMITTED, $invite->status);
        $this->assertSame('Rao', $hire->last_name);
        $this->assertSame('ABCDE1234F', $hire->statutoryDetail->pan);
        $this->assertSame('prejoining', $hire->documents()->first()->source);
        Notification::assertSentTo($this->hr, PerformanceNotification::class);

        // Locked after submission.
        $this->get($url)->assertSee('Your details were submitted')->assertDontSee('Submit to HR');
        $this->post($url, ['last_name' => 'Late'])->assertSessionHasErrors('submit');
        $this->assertSame('Rao', $hire->fresh()->last_name);

        // HR sends back -> editable again; then marks reviewed.
        $service->reopen($invite, $this->hr, 'Upload Aadhaar card too');
        $this->get($url)->assertSee('Upload Aadhaar card too')->assertSee('Submit to HR');
        $this->post($url, ['action' => 'submit'])->assertSessionHasNoErrors();
        $service->approve($invite->fresh(), $this->hr);
        $this->assertSame(PrejoiningInvite::STATUS_APPROVED, $invite->fresh()->status);

        // A revoked link is dead.
        $service->revoke($invite->fresh());
        $this->get($url)->assertNotFound();
    }

    public function test_prejoining_link_expires_and_bad_tokens_404(): void
    {
        $hire = $this->makeEmployee('NEW002', ['date_of_joining' => '2026-11-25']);
        app(PrejoiningService::class)->invite($hire, 'n2@example.com', $this->hr);

        $this->get('/joining/not-a-real-token')->assertNotFound();

        Carbon::setTestNow(now()->addDays(PrejoiningService::LINK_VALID_DAYS + 1));
        $invite = PrejoiningInvite::sole();
        $this->assertFalse($invite->isUsable());

        // Long-standing employees use My Profile, not the portal.
        $this->expectException(ValidationException::class);
        app(PrejoiningService::class)->invite($this->employee->employee, 'x@example.com', $this->hr);
    }

    public function test_hr_prejoining_screen(): void
    {
        $hire = $this->makeEmployee('NEW003', ['date_of_joining' => '2026-11-25']);
        app(PrejoiningService::class)->invite($hire, 'n3@example.com', $this->hr);

        $this->actingAs($this->hr)->get('/admin/prejoining-invites')->assertOk()->assertSee('NEW003');
        $this->flushSession();
        $this->actingAs($this->employee)->get('/admin/prejoining-invites')->assertForbidden();
    }

    // ---- PMS export -------------------------------------------------------------------------

    public function test_pms_export(): void
    {
        $manager = $this->makeUser('MGR001', 'Manager');
        $this->employee->employee->update(['reporting_manager_id' => $manager->employee_id]);
        $cycle = AppraisalCycle::create(['name' => 'FY 2026-27', 'period_start' => '2026-04-01', 'period_end' => '2027-03-31', 'min_goals' => 1]);
        app(AppraisalService::class)->launch($cycle);

        $url = route('reports.download', ['type' => 'appraisal', 'cycle' => $cycle->id]);

        $response = $this->actingAs($this->hr)->get($url);
        $response->assertOk();
        $this->assertStringContainsString('appraisals-fy-2026-27.xlsx', $response->headers->get('content-disposition'));

        $this->actingAs($manager)->get($url)->assertOk();
        $this->actingAs($this->hr)->get('/admin/reports')->assertOk()->assertSee('Performance Appraisals');

        // Someone with no appraisal in that cycle gets nothing.
        $outsider = $this->makeUser('EMP003', 'Employee');
        Appraisal::where('employee_id', $outsider->employee_id)->delete();
        $this->actingAs($outsider)->get($url)->assertForbidden();
    }
}
