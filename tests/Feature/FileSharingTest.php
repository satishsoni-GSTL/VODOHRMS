<?php

namespace Tests\Feature;

use App\Filament\Resources\SharedFileResource\Pages\CreateSharedFile;
use App\Filament\Resources\SharedFileResource\Pages\ViewSharedFile;
use App\Filament\Resources\SharedFileResource\RelationManagers\CommentsRelationManager;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SharedFile;
use App\Models\SharedFileComment;
use App\Models\SharedFileRecipient;
use App\Models\User;
use App\Notifications\SharedFileCommentNotification;
use App\Notifications\SharedFileInvitationNotification;
use App\Notifications\SharedFileOtpNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FileSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
    }

    private function makeUser(string $code, string $role = 'Employee'): User
    {
        $company = Company::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);

        $employee = Employee::create([
            'employee_code' => $code,
            'first_name' => $code,
            'official_email' => strtolower($code).'@vodohrms.local',
            'company_id' => $company->id,
            'date_of_joining' => now(),
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'employee_code' => $code,
            'name' => $code,
            'email' => $employee->official_email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function makeShare(User $owner, string $email = 'client@example.com', array $fileAttrs = []): SharedFileRecipient
    {
        $file = SharedFile::create(array_merge([
            'owner_id' => $owner->id,
            'title' => 'Offer Letter',
            'file_path' => 'shared-files/offer.pdf',
            'file_name' => 'offer.pdf',
            'mime_type' => 'application/pdf',
            'size' => 17,
        ], $fileAttrs));
        Storage::disk('local')->put($file->file_path, '%PDF-1.4 dummy pdf');

        return $file->recipients()->create(['email' => $email, 'name' => 'Client', 'can_comment' => true]);
    }

    /** Runs the email-code flow and returns the plain link token. */
    private function verify(SharedFileRecipient $recipient): string
    {
        $token = $recipient->token;

        $this->post(route('fileshare.send-code', $token))->assertRedirect(route('fileshare.show', $token));

        $otp = null;
        Notification::assertSentTo($recipient, SharedFileOtpNotification::class, function ($n) use ($recipient, &$otp) {
            $otp = $n->toMail($recipient)->introLines[1];

            return true;
        });

        $this->post(route('fileshare.verify', $token), ['code' => $otp])->assertRedirect(route('fileshare.show', $token));

        return $token;
    }

    public function test_owner_shares_file_from_portal_and_recipients_are_invited(): void
    {
        $owner = $this->makeUser('EMP900');
        $colleague = $this->makeUser('EMP901');
        $this->actingAs($owner);

        Livewire::test(CreateSharedFile::class)
            ->fillForm([
                'title' => 'Q3 Report',
                'description' => 'Please review.',
                'file_path' => UploadedFile::fake()->createWithContent('q3.pdf', '%PDF-1.4 quarterly numbers'),
                'allow_download' => true,
                'recipients' => [
                    ['email' => 'Partner@External.com', 'name' => 'Partner', 'can_comment' => true],
                    ['user_id' => $colleague->id, 'email' => $colleague->email, 'name' => $colleague->name, 'can_comment' => false],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $file = SharedFile::where('title', 'Q3 Report')->firstOrFail();
        $this->assertSame($owner->id, $file->owner_id);
        $this->assertSame(2, $file->recipients()->count());
        $this->assertGreaterThan(0, $file->size);

        $external = $file->recipients()->where('email', 'partner@external.com')->firstOrFail();
        $this->assertNotNull($external->invited_at);
        $this->assertSame(64, strlen($external->token_hash));
        Notification::assertSentTo($external, SharedFileInvitationNotification::class, function ($n) use ($external) {
            return $n->toMail($external)->actionUrl === $external->shareUrl();
        });

        // The colleague sees it under "Shared With Me"; nobody else can open the owner's record.
        $this->flushSession();
        $this->actingAs($colleague);
        $this->get('/admin/shared-with-me')->assertOk()->assertSee('Q3 Report');
        $this->get('/admin/shared-files/'.$file->id)->assertNotFound();
        $this->get(route('shared-files.download', $file))->assertForbidden();
    }

    public function test_link_requires_email_code_before_file_is_shown(): void
    {
        $recipient = $this->makeShare($this->makeUser('EMP910'));
        $token = $recipient->token;

        $this->get(route('fileshare.show', $token))->assertOk()->assertSee('Verify it')->assertDontSee('Comments (');
        $this->get(route('fileshare.download', $token))->assertForbidden();
        $this->get(route('fileshare.preview', $token))->assertForbidden();

        $this->post(route('fileshare.send-code', $token));
        $this->post(route('fileshare.verify', $token), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->get(route('fileshare.download', $token))->assertForbidden();

        $this->verify($recipient);

        $this->get(route('fileshare.show', $token))->assertOk()->assertSee('Offer Letter')->assertSee('Comments (0)');
        $this->get(route('fileshare.download', $token))->assertOk()->assertDownload('offer.pdf');
        $this->get(route('fileshare.preview', $token))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(1, $recipient->fresh()->access_count);
        $this->assertDatabaseHas('shared_file_access_logs', ['recipient_id' => $recipient->id, 'action' => 'verified']);
        $this->assertDatabaseHas('shared_file_access_logs', ['recipient_id' => $recipient->id, 'action' => 'downloaded']);
    }

    public function test_code_is_locked_after_too_many_wrong_attempts(): void
    {
        $recipient = $this->makeShare($this->makeUser('EMP915'));
        $token = $recipient->token;

        $this->post(route('fileshare.send-code', $token));
        $otp = null;
        Notification::assertSentTo($recipient, SharedFileOtpNotification::class, function ($n) use ($recipient, &$otp) {
            $otp = $n->toMail($recipient)->introLines[1];

            return true;
        });

        $wrong = $otp === '111111' ? '222222' : '111111';
        foreach (range(1, config('fileshare.otp_max_attempts')) as $i) {
            $this->post(route('fileshare.verify', $token), ['code' => $wrong]);
        }

        // Even the right code is now refused until a new one is requested.
        $this->post(route('fileshare.verify', $token), ['code' => $otp])->assertSessionHasErrors('code');
    }

    public function test_download_can_be_disabled_and_revoked_or_expired_links_stop_working(): void
    {
        $owner = $this->makeUser('EMP920');
        $recipient = $this->makeShare($owner, fileAttrs: ['allow_download' => false]);
        $token = $this->verify($recipient);

        $this->get(route('fileshare.download', $token))->assertForbidden();
        $this->get(route('fileshare.preview', $token))->assertOk();

        $recipient->sharedFile->update(['expires_at' => now()->subMinute()]);
        $this->get(route('fileshare.show', $token))->assertNotFound()->assertSee('link isn');

        $recipient->sharedFile->update(['expires_at' => null]);
        $this->get(route('fileshare.show', $token))->assertOk();

        $recipient->forceFill(['revoked_at' => now()])->save();
        $this->get(route('fileshare.show', $token))->assertNotFound();
        $this->get(route('fileshare.preview', $token))->assertNotFound();

        $this->get(route('fileshare.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_recipient_comments_and_owner_replies_with_notifications(): void
    {
        $owner = $this->makeUser('EMP930');
        $recipient = $this->makeShare($owner);
        $other = $recipient->sharedFile->recipients()->create(['email' => 'other@example.com']);
        $token = $this->verify($recipient);

        $this->post(route('fileshare.comment', $token), ['body' => 'Is clause 4 final?'])->assertRedirect();

        $root = SharedFileComment::firstOrFail();
        $this->assertSame($recipient->id, $root->recipient_id);
        $this->assertSame('Client', $root->author_name);
        Notification::assertSentTo($owner, SharedFileCommentNotification::class);
        Notification::assertNotSentTo($other, SharedFileCommentNotification::class);

        // Owner replies from the portal → only people in that thread hear about it.
        $this->actingAs($owner);
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $recipient->sharedFile, 'pageClass' => ViewSharedFile::class])
            ->callTableAction('reply', $root, data: ['body' => 'Yes, it is final.'])
            ->assertHasNoTableActionErrors();

        $reply = SharedFileComment::whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($root->id, $reply->parent_id);
        $this->assertSame($owner->id, $reply->user_id);
        Notification::assertSentTo($recipient, SharedFileCommentNotification::class);
        Notification::assertNotSentTo($other, SharedFileCommentNotification::class);

        // A reply to a reply still hangs off the root comment.
        $this->post(route('fileshare.comment', $token), ['body' => 'Thanks!', 'parent_id' => $reply->id]);
        $this->assertSame($root->id, SharedFileComment::latest('id')->first()->parent_id);

        $this->get(route('fileshare.show', $token))->assertSee('Yes, it is final.')->assertSee('Owner')->assertSee('Comments (3)');

        // Commenting can be switched off per person; a parent from another file is rejected.
        $recipient->update(['can_comment' => false]);
        $this->post(route('fileshare.comment', $token), ['body' => 'More'])->assertForbidden();
    }

    public function test_file_share_domain_only_serves_share_pages(): void
    {
        config(['fileshare.domain' => 'file.globalspace.test']);
        $recipient = $this->makeShare($this->makeUser('EMP940'));

        $this->get('http://file.globalspace.test/admin/login')->assertNotFound();
        $this->get('http://file.globalspace.test/')->assertNotFound();
        $this->get('http://file.globalspace.test/s/'.$recipient->token)->assertOk();
        $this->get('http://localhost/admin/login')->assertOk();
    }

    public function test_hr_with_manage_permission_sees_all_shared_files(): void
    {
        $owner = $this->makeUser('EMP950');
        $hr = $this->makeUser('HR950', 'HR Admin');
        $recipient = $this->makeShare($owner);

        $this->actingAs($hr);
        $this->get('/admin/shared-files')->assertOk()->assertSee('Offer Letter');
        $this->get('/admin/shared-files/'.$recipient->shared_file_id)->assertOk();

        $this->flushSession();
        $this->actingAs($this->makeUser('EMP951'));
        $this->get('/admin/shared-files')->assertOk()->assertDontSee('Offer Letter');
    }
}
