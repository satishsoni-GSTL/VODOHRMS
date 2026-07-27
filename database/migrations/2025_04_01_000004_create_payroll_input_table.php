<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('payroll_month', 7);
            // YYYY-MM
            $table->string('type');
            // bonus, incentive, arrears, additional_earning, additional_deduction,
            // reimbursement, lop_adjustment, loan_deduction, salary_advance, tds_adjustment
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'payroll_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_inputs');
    }
};
