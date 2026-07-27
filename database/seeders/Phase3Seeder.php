<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use Illuminate\Database\Seeder;

class Phase3Seeder extends Seeder
{
    public function run(): void
    {
        $definition = WorkflowDefinition::firstOrCreate(
            ['module' => WorkflowDefinition::MODULE_EXPENSE],
            ['name' => 'Expense Claim Approval', 'is_active' => true]
        );

        if (! $definition->levels()->exists()) {
            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => 1,
                'approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER,
            ]);

            // Department Head only steps in for claims above the threshold.
            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => 2,
                'approver_type' => WorkflowLevel::APPROVER_DEPARTMENT_HEAD,
                'condition_rules' => [['field' => 'amount', 'operator' => '>', 'value' => 10000]],
            ]);

            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => 3,
                'approver_type' => WorkflowLevel::APPROVER_FINANCE,
            ]);
        }

        $categories = [
            ['name' => 'Local Travel', 'code' => 'TRAVEL_LOCAL', 'requires_bill' => true],
            ['name' => 'Taxi', 'code' => 'TAXI', 'requires_bill' => true],
            ['name' => 'Food', 'code' => 'FOOD', 'requires_bill' => true],
            ['name' => 'Hotel', 'code' => 'HOTEL', 'requires_bill' => true],
            ['name' => 'Airfare', 'code' => 'AIRFARE', 'requires_bill' => true],
            ['name' => 'Mobile', 'code' => 'MOBILE', 'requires_bill' => false],
            ['name' => 'Internet', 'code' => 'INTERNET', 'requires_bill' => false],
            ['name' => 'Client Meeting', 'code' => 'CLIENT_MEETING', 'requires_bill' => true, 'requires_project' => true],
            ['name' => 'Software', 'code' => 'SOFTWARE', 'requires_bill' => true],
            ['name' => 'Miscellaneous', 'code' => 'MISC', 'requires_bill' => false],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(['code' => $category['code']], $category + ['is_active' => true]);
        }
    }
}
