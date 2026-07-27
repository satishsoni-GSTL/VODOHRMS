<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts a query (assumed to have an `employee_id` column) to what the given user
 * may see: HR-tier roles (holding $hrPermission) see everything, managers see their own
 * record plus their direct/indirect reports, everyone else sees only their own record.
 */
class ScopesToOwnTeam
{
    public static function apply(Builder $query, User $user, string $hrPermission): Builder
    {
        if ($user->can($hrPermission)) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        $visibleIds = [$employee->id, ...$employee->allSubordinateIds()];

        return $query->whereIn('employee_id', $visibleIds);
    }
}
