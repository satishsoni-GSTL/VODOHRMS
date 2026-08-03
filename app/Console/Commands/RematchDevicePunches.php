<?php

namespace App\Console\Commands;

use App\Models\DevicePunchLog;
use App\Services\BiometricPunchService;
use Illuminate\Console\Command;

class RematchDevicePunches extends Command
{
    protected $signature = 'biometric:rematch';

    protected $description = 'Re-attempt matching unmatched biometric punch logs to employees (e.g. after fixing a biometric_enroll_id).';

    public function handle(BiometricPunchService $punchService): int
    {
        $unmatched = DevicePunchLog::where('status', DevicePunchLog::STATUS_UNMATCHED)->get();

        $matched = 0;

        foreach ($unmatched as $log) {
            if ($punchService->rematch($log)) {
                $matched++;
            }
        }

        $this->info("Rematched {$matched} of {$unmatched->count()} unmatched punches.");

        return self::SUCCESS;
    }
}
