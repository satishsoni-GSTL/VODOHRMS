<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rename employee codes that carry an old prefix (e.g. the "LEG" minted by the legacy
 * import) to a new one (e.g. "GS"), keeping the numeric suffix. Updates both the
 * employees row and its denormalised copy on users. Dry run unless --commit is passed.
 */
class ReprefixEmployeeCodes extends Command
{
    protected $signature = 'employees:reprefix-codes
        {--from=LEG : Current prefix to replace}
        {--to=GS : New prefix}
        {--commit : Persist the changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Swap the prefix on employee codes (e.g. LEG001 -> GS001) on both employees and users.';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $commit = (bool) $this->option('commit');

        if ($from === '' || $to === '' || $from === $to) {
            $this->error('Provide distinct non-empty --from and --to prefixes.');

            return self::FAILURE;
        }

        $employees = Employee::withTrashed()
            ->where('employee_code', 'like', $from.'%')
            ->orderBy('employee_code')
            ->get();

        if ($employees->isEmpty()) {
            $this->info("No employee codes start with \"{$from}\". Nothing to do.");

            return self::SUCCESS;
        }

        $planned = [];
        $blocked = [];

        foreach ($employees as $employee) {
            $newCode = $to.Str::after($employee->employee_code, $from);

            $clash = Employee::withTrashed()
                ->where('employee_code', $newCode)
                ->whereKeyNot($employee->getKey())
                ->exists();

            if ($clash) {
                $blocked[] = [$employee->employee_code, $newCode, 'target code already exists'];

                continue;
            }

            $planned[] = [$employee->id, $employee->employee_code, $newCode];
        }

        $this->table(['Employee ID', 'Old code', 'New code'], $planned);

        if ($blocked !== []) {
            $this->warn('Skipped '.count($blocked).' — resolve the clash by hand:');
            $this->table(['Old code', 'Wanted', 'Reason'], $blocked);
        }

        if (! $commit) {
            $this->comment('Dry run — re-run with --commit to apply '.count($planned).' change(s).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($planned) {
            foreach ($planned as [$id, $oldCode, $newCode]) {
                Employee::withTrashed()->whereKey($id)->update(['employee_code' => $newCode]);
                User::where('employee_id', $id)->update(['employee_code' => $newCode]);
            }
        });

        $this->info('Updated '.count($planned).' employee code(s).');

        return self::SUCCESS;
    }
}
