<?php

use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmation workflow: the reporting manager (or HR) reviews an employee nearing the end of
 * probation and recommends Confirm or Extend; the recommendation is approved through the
 * standard approval engine (default: one HR level), after which the employee is confirmed or
 * their probation extended and a letter is generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('probation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('probation_end_date');
            $table->string('recommendation'); // confirm, extend
            $table->unsignedSmallInteger('extension_days')->nullable();
            // Confirmation date (confirm) or the new probation end date (extend).
            $table->date('effective_date');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->text('performance_summary');
            $table->text('improvement_areas')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected, sent_back
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('letter_path')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        $definition = WorkflowDefinition::firstOrCreate(
            ['module' => WorkflowDefinition::MODULE_PROBATION_CONFIRMATION],
            ['name' => 'Probation Confirmation Approval', 'is_active' => true],
        );

        if (! $definition->levels()->exists()) {
            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => 1,
                'approver_type' => WorkflowLevel::APPROVER_HR,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('probation_reviews');
    }
};
