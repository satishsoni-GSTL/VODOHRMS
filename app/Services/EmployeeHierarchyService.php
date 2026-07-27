<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeHierarchyService
{
    /**
     * Prevent A reporting to B when B already (directly or indirectly) reports to A.
     */
    public function wouldCreateCircularReference(?int $employeeId, ?int $candidateManagerId): bool
    {
        if ($candidateManagerId === null || $employeeId === null) {
            return false;
        }

        if ($candidateManagerId === $employeeId) {
            return true;
        }

        $currentId = $candidateManagerId;
        $visited = [];

        while ($currentId !== null) {
            if ($currentId === $employeeId) {
                return true;
            }

            if (in_array($currentId, $visited, true)) {
                break; // pre-existing cycle unrelated to this change; stop walking
            }
            $visited[] = $currentId;

            $currentId = Employee::query()->whereKey($currentId)->value('reporting_manager_id');
        }

        return false;
    }
}
