<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off reconciliation for logins whose `email` drifted from the employee's current
 * official/personal email (typically after a domain change such as globalspace.in ->
 * makebot.in). Notifications route via $user->email, so a stale value silently sends to
 * the old address. Runs as a dry run unless --commit is passed.
 */
class SyncLoginEmails extends Command
{
    protected $signature = 'users:sync-login-emails
        {--commit : Persist the changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Realign each login\'s email with its employee\'s current official/personal email.';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $employees = Employee::query()
            ->whereHas('user')
            ->with('user')
            ->orderBy('employee_code')
            ->get();

        $changed = [];
        $blocked = [];

        foreach ($employees as $employee) {
            $user = $employee->user;
            $target = $employee->official_email ?: $employee->personal_email;

            if (! $target || strcasecmp($target, (string) $user->email) === 0) {
                continue;
            }

            if (User::where('email', $target)->whereKeyNot($user->getKey())->exists()) {
                $blocked[] = [$employee->employee_code, $user->email, $target, 'target in use by another login'];

                continue;
            }

            $changed[] = [$employee->employee_code, $user->email, $target];

            if ($commit) {
                $user->forceFill(['email' => $target])->save();
            }
        }

        if ($changed !== []) {
            $this->info(($commit ? 'Updated' : 'Would update').' '.count($changed).' login(s):');
            $this->table(['Employee', 'Old email', 'New email'], $changed);
        }

        if ($blocked !== []) {
            $this->warn('Skipped '.count($blocked).' login(s) — resolve the address clash by hand:');
            $this->table(['Employee', 'Current login email', 'Wanted', 'Reason'], $blocked);
        }

        if ($changed === [] && $blocked === []) {
            $this->info('All logins already match their employee email. Nothing to do.');
        } elseif (! $commit && $changed !== []) {
            $this->newLine();
            $this->comment('Dry run — re-run with --commit to apply.');
        }

        return self::SUCCESS;
    }
}
