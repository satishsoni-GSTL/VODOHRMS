<?php

namespace App\Services;

use App\Models\ExitClearance;
use App\Models\Resignation;
use App\Models\User;

class ExitClearanceService
{
    public function clear(ExitClearance $clearance, User $user, ?string $remarks = null): void
    {
        $clearance->update([
            'status' => ExitClearance::STATUS_CLEARED,
            'remarks' => $remarks,
            'cleared_by' => $user->id,
            'cleared_at' => now(),
        ]);
    }

    public function reject(ExitClearance $clearance, User $user, string $remarks): void
    {
        $clearance->update([
            'status' => ExitClearance::STATUS_REJECTED,
            'remarks' => $remarks,
            'cleared_by' => $user->id,
            'cleared_at' => now(),
        ]);
    }

    public function allCleared(Resignation $resignation): bool
    {
        return $resignation->exitClearances()->where('status', '!=', ExitClearance::STATUS_CLEARED)->doesntExist();
    }
}
