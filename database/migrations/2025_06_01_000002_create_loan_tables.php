<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type');
            // loan, salary_advance
            $table->decimal('requested_amount', 12, 2);
            $table->text('reason');
            $table->date('request_date');
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->unsignedInteger('installments')->nullable();
            $table->decimal('monthly_recovery', 12, 2)->nullable();
            $table->string('recovery_start_month', 7)->nullable();
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('status')->default('pending');
            // pending, manager_approved, hr_approved, finance_approved, active, closed, rejected
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_loan_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees')->cascadeOnDelete();
            $table->decimal('recovered_amount', 12, 2);
            $table->string('recovery_month', 7);
            $table->timestamps();

            $table->unique(['employee_loan_id', 'payroll_run_employee_id'], 'loan_recovery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_recoveries');
        Schema::dropIfExists('employee_loans');
    }
};
