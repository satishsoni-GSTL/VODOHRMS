<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            // leave, attendance_regularization, expense, loan, salary_advance, resignation, other
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('approver_type');
            // reporting_manager, department_head, hr, finance, management, specific_role, specific_user
            // approver_role_id references spatie's roles table (package-owned, no FK constraint to avoid migration-order coupling)
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('condition_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->string('requestable_type');
            $table->unsignedBigInteger('requestable_id');
            $table->unsignedInteger('current_level')->default(1);
            $table->string('status')->default('pending');
            // pending, approved, rejected, sent_back, cancelled
            $table->timestamps();

            $table->index(['requestable_type', 'requestable_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_instance_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            // approve, reject, send_back
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_instances');
        Schema::dropIfExists('workflow_levels');
        Schema::dropIfExists('workflow_definitions');
    }
};
