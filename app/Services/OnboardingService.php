<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OnboardingChecklist;

class OnboardingService
{
    /**
     * Recompute every checklist flag from the employee's actual current data, rather than
     * trusting manually-ticked checkboxes that can drift out of sync with reality.
     */
    public function refresh(Employee $employee): OnboardingChecklist
    {
        $checklist = OnboardingChecklist::firstOrCreate(['employee_id' => $employee->id]);

        $checklist->personal_details_done = filled($employee->first_name) && filled($employee->dob) && filled($employee->personal_mobile);
        $checklist->documents_done = $employee->documents()->exists();
        $checklist->statutory_done = filled($employee->statutoryDetail?->pan) && filled($employee->statutoryDetail?->uan);
        $checklist->bank_done = $employee->bankDetails()->exists();
        $checklist->department_done = filled($employee->department_id) && filled($employee->designation_id) && filled($employee->reporting_manager_id);
        $checklist->salary_done = $employee->currentSalaryStructure() !== null;
        $checklist->login_done = $employee->user()->exists();
        $checklist->asset_allocation_done = $employee->assets()->exists();

        $checklist->recalculateCompletion();
        $checklist->save();

        return $checklist;
    }
}
