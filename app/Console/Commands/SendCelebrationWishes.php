<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Notifications\CelebrationWishNotification;
use App\Services\CelebrationWishService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 9 AM: send each employee celebrating a birthday or work anniversary today a personal wish
 * on the mobile app. Safe to re-run — each wish is sent at most once per day.
 */
class SendCelebrationWishes extends Command
{
    protected $signature = 'hr:send-celebration-wishes';

    protected $description = 'Push personal birthday / work-anniversary wishes to today\'s celebrants (mobile app)';

    private const ELIGIBLE_STATUSES = [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD];

    public function handle(CelebrationWishService $wishes): int
    {
        $today = now();
        $sent = 0;

        $employees = Employee::query()
            ->with('user')
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->where(fn ($q) => $q->whereNotNull('dob')->orWhereNotNull('date_of_joining'))
            ->get();

        foreach ($employees as $employee) {
            if (! $employee->user?->is_active) {
                continue;
            }

            foreach ($wishes->wishesFor($employee, $today) as $wish) {
                // Once per employee, type and day, even if the scheduler runs twice.
                if (! Cache::add("celebration-wish:{$employee->id}:{$wish['type']}:{$today->toDateString()}", true, now()->addDays(2))) {
                    continue;
                }

                try {
                    $employee->user->notify(new CelebrationWishNotification($wish['title'], $wish['message']));
                    $sent++;
                    $this->line("{$wish['type']}: {$employee->employee_code}");
                } catch (Throwable $e) {
                    Log::warning('Celebration wish failed', ['employee' => $employee->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Celebration wishes sent: {$sent}.");

        return self::SUCCESS;
    }
}
