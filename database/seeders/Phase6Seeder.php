<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use Illuminate\Database\Seeder;

class Phase6Seeder extends Seeder
{
    public function run(): void
    {
        $loan = WorkflowDefinition::firstOrCreate(
            ['module' => WorkflowDefinition::MODULE_LOAN],
            ['name' => 'Loan / Advance Approval', 'is_active' => true]
        );

        if (! $loan->levels()->exists()) {
            WorkflowLevel::create([
                'workflow_definition_id' => $loan->id,
                'sequence' => 1,
                'approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER,
            ]);

            WorkflowLevel::create([
                'workflow_definition_id' => $loan->id,
                'sequence' => 2,
                'approver_type' => WorkflowLevel::APPROVER_HR,
            ]);

            WorkflowLevel::create([
                'workflow_definition_id' => $loan->id,
                'sequence' => 3,
                'approver_type' => WorkflowLevel::APPROVER_FINANCE,
            ]);
        }

        $resignation = WorkflowDefinition::firstOrCreate(
            ['module' => WorkflowDefinition::MODULE_RESIGNATION],
            ['name' => 'Resignation Acceptance', 'is_active' => true]
        );

        if (! $resignation->levels()->exists()) {
            WorkflowLevel::create([
                'workflow_definition_id' => $resignation->id,
                'sequence' => 1,
                'approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER,
            ]);

            WorkflowLevel::create([
                'workflow_definition_id' => $resignation->id,
                'sequence' => 2,
                'approver_type' => WorkflowLevel::APPROVER_HR,
            ]);
        }
    }
}
