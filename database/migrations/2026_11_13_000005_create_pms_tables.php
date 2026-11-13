<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance management: appraisal cycles, one appraisal per employee per cycle, and the
 * weighted goals (KRAs) each appraisal is rated on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('goal_setting_due')->nullable();
            $table->date('review_due')->nullable();
            $table->unsignedTinyInteger('min_goals')->default(3);
            $table->string('status')->default('draft'); // draft, goal_setting, review, closed
            $table->timestamps();
        });

        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_cycle_id')->constrained('appraisal_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            // Reviewer: the reporting manager when the cycle was launched (HR can change it).
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            // goals_draft, goals_submitted, goals_approved, self_submitted, manager_submitted, closed
            $table->string('status')->default('goals_draft');
            $table->text('goals_return_remarks')->nullable();
            $table->text('self_overall_comments')->nullable();
            $table->text('manager_overall_comments')->nullable();
            $table->decimal('self_score', 4, 2)->nullable();
            $table->decimal('manager_score', 4, 2)->nullable();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->text('final_remarks')->nullable();
            $table->timestamp('goals_submitted_at')->nullable();
            $table->timestamp('goals_approved_at')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['appraisal_cycle_id', 'employee_id']);
            $table->index(['manager_id', 'status']);
        });

        Schema::create('appraisal_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained('appraisals')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('target')->nullable(); // how success is measured
            $table->unsignedTinyInteger('weightage'); // %; an appraisal's goals add up to 100
            $table->unsignedTinyInteger('self_rating')->nullable(); // 1-5
            $table->text('self_comments')->nullable();
            $table->unsignedTinyInteger('manager_rating')->nullable(); // 1-5
            $table->text('manager_comments')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_goals');
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('appraisal_cycles');
    }
};
