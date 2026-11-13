<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PrejoiningInvite;
use App\Models\User;
use App\Notifications\Concerns\NotifiesRecipients;
use App\Notifications\PerformanceNotification;
use App\Notifications\PrejoiningInviteNotification;
use App\Notifications\PrejoiningOtpNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Pre-joining portal: HR invites a new hire by email; the hire opens the link, confirms a
 * one-time code sent to that email, fills in their details and documents, and submits.
 * HR then reviews (and can reopen with remarks).
 */
class PrejoiningService
{
    use NotifiesRecipients;

    public const LINK_VALID_DAYS = 21;

    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public const OTP_MAX_SENDS = 3; // per 10 minutes

    public const SESSION_HOURS = 4;

    public function __construct(private readonly EmployeeProfileService $profile) {}

    public function activeInvite(Employee $employee): ?PrejoiningInvite
    {
        return $employee->prejoiningInvites()->where('status', '!=', PrejoiningInvite::STATUS_REVOKED)->latest('id')->first();
    }

    /** Create (or replace) the invite and email the link. Returns the invite. */
    public function invite(Employee $employee, string $email, User $by): PrejoiningInvite
    {
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        if ($employee->date_of_joining && $employee->date_of_joining->lt(today()->subDays(30))) {
            throw ValidationException::withMessages(['email' => 'This employee joined more than 30 days ago — use My Profile instead of the pre-joining portal.']);
        }

        $token = Str::random(48);

        $invite = DB::transaction(function () use ($employee, $email, $by, $token) {
            // Only one live link per new hire.
            $employee->prejoiningInvites()->whereIn('status', [PrejoiningInvite::STATUS_SENT, PrejoiningInvite::STATUS_SUBMITTED])
                ->update(['status' => PrejoiningInvite::STATUS_REVOKED]);

            if (blank($employee->personal_email)) {
                $employee->update(['personal_email' => $email]);
            }

            return PrejoiningInvite::create([
                'employee_id' => $employee->id,
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(self::LINK_VALID_DAYS),
                'status' => PrejoiningInvite::STATUS_SENT,
                'sent_by' => $by->id,
            ]);
        });

        $invite->notifyNow(new PrejoiningInviteNotification($invite, route('prejoining.show', $token)));

        return $invite;
    }

    public function revoke(PrejoiningInvite $invite): void
    {
        $invite->update(['status' => PrejoiningInvite::STATUS_REVOKED]);
    }

    // ---- One-time code ----------------------------------------------------------------

    /** Returns null when sent, or the seconds to wait when rate-limited. */
    public function sendOtp(PrejoiningInvite $invite): ?int
    {
        $key = 'prejoining-otp:'.$invite->id;

        if (RateLimiter::tooManyAttempts($key, self::OTP_MAX_SENDS)) {
            return RateLimiter::availableIn($key);
        }

        RateLimiter::hit($key, 600);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $invite->forceFill([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_attempts' => 0,
        ])->save();

        $invite->notifyNow(new PrejoiningOtpNotification($invite, $otp));

        return null;
    }

    public function verifyOtp(PrejoiningInvite $invite, string $code): bool
    {
        $valid = $invite->otp_hash !== null
            && $invite->otp_expires_at?->isFuture()
            && $invite->otp_attempts < self::OTP_MAX_ATTEMPTS
            && Hash::check($code, $invite->otp_hash);

        if (! $valid) {
            if ($invite->otp_hash !== null) {
                $invite->increment('otp_attempts');
            }

            return false;
        }

        $invite->forceFill(['otp_hash' => null, 'otp_expires_at' => null, 'otp_attempts' => 0, 'last_accessed_at' => now()])->save();

        return true;
    }

    // ---- Hire side --------------------------------------------------------------------

    public function save(PrejoiningInvite $invite, array $data): void
    {
        $this->assertEditable($invite);
        $this->profile->update($invite->employee, $data, EmployeeProfileService::MODE_PREJOINING);
    }

    public function submit(PrejoiningInvite $invite): void
    {
        $this->assertEditable($invite);

        $missing = $this->profile->missingDetails($invite->employee->fresh());

        if ($missing !== []) {
            throw ValidationException::withMessages(['submit' => 'Please fill in: '.implode(', ', $missing).'.']);
        }

        $invite->update(['status' => PrejoiningInvite::STATUS_SUBMITTED, 'submitted_at' => now()]);

        $employee = $invite->employee;
        $name = trim(preg_replace('/\s+/', ' ', $employee->full_name));
        $hrUsers = $employee->hrManager?->user
            ? [$employee->hrManager->user]
            : (Role::where('name', 'HR Admin')->exists() ? User::role('HR Admin')->get()->all() : []);

        $this->notifyUsers($hrUsers, new PerformanceNotification(
            "Pre-joining details submitted: {$name}",
            "{$name} ({$employee->employee_code}) has submitted their pre-joining details and documents. Please review them.",
            '/admin/prejoining-invites',
            'Review Pre-joining Details',
        ));
    }

    private function assertEditable(PrejoiningInvite $invite): void
    {
        if (! $invite->isEditable()) {
            throw ValidationException::withMessages(['submit' => 'Your details have already been submitted. Contact HR if something needs to change.']);
        }
    }

    // ---- HR side ----------------------------------------------------------------------

    public function approve(PrejoiningInvite $invite, User $by, ?string $remarks = null): void
    {
        if ($invite->status !== PrejoiningInvite::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted details can be marked reviewed.']);
        }

        $invite->update(['status' => PrejoiningInvite::STATUS_APPROVED, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_remarks' => $remarks]);
    }

    /** Send it back to the hire to correct; the same link works again. */
    public function reopen(PrejoiningInvite $invite, User $by, string $remarks): void
    {
        if (! in_array($invite->status, [PrejoiningInvite::STATUS_SUBMITTED, PrejoiningInvite::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['status' => 'Only submitted details can be reopened.']);
        }

        if (blank($remarks)) {
            throw ValidationException::withMessages(['review_remarks' => 'Tell the new hire what to correct.']);
        }

        $invite->update([
            'status' => PrejoiningInvite::STATUS_SENT,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
            'expires_at' => $invite->expires_at->isPast() ? now()->addDays(7) : $invite->expires_at,
        ]);

        $invite->notifyNow(new PerformanceNotification(
            'Please update your joining details',
            "HR has asked you to correct your joining details: {$remarks} Please open the link from your invitation email again to make the changes.",
            null,
        ));
    }
}
