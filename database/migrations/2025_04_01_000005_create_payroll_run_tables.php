<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_month', 7);
            // YYYY-MM
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status')->default('draft');
            // draft, calculated, reviewed, finalized, locked, reopened
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopened_reason')->nullable();
            $table->timestamps();

            $table->unique(['payroll_month', 'company_id']);
        });

        Schema::create('payroll_run_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained('employee_salary_structures')->nullOnDelete();
            $table->decimal('paid_days', 5, 2)->default(0);
            $table->decimal('lop_days', 5, 2)->default(0);
            $table->decimal('gross_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('employer_contributions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('status')->default('calculated');
            // calculated, reviewed, finalized
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            // prevents double payroll generation for the same employee/month at the DB level
        });

        Schema::create('payroll_run_employee_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->string('component_type');
            // earning, deduction, employer_contribution
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_employee_lines');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
    }
};
