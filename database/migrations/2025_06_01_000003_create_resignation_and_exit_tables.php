<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('resignation_date');
            $table->text('reason');
            $table->date('requested_last_working_date');
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('hr_comments')->nullable();
            $table->date('approved_last_working_date')->nullable();
            $table->string('status')->default('pending');
            // pending, manager_approved, hr_approved, rejected, withdrawn
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exit_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resignation_id')->constrained('resignations')->cascadeOnDelete();
            $table->string('department');
            // manager, it, admin, finance, hr
            $table->string('status')->default('pending');
            // pending, cleared, rejected
            $table->text('remarks')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['resignation_id', 'department'], 'exit_clearance_unique');
        });

        Schema::create('full_final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resignation_id')->unique()->constrained('resignations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('pending_salary', 12, 2)->default(0);
            $table->decimal('bonus_incentive', 12, 2)->default(0);
            $table->decimal('reimbursement', 12, 2)->default(0);
            $table->decimal('leave_encashment', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('notice_recovery', 12, 2)->default(0);
            $table->decimal('loan_recovery', 12, 2)->default(0);
            $table->decimal('advance_recovery', 12, 2)->default(0);
            $table->decimal('tds', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->string('status')->default('draft');
            // draft, calculated, approved, paid
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('full_final_settlements');
        Schema::dropIfExists('exit_clearances');
        Schema::dropIfExists('resignations');
    }
};
