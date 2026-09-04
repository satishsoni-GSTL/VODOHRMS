<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_deduction_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('source_type');
            // payroll_input, salary_structure_line, statutory
            $table->unsignedBigInteger('source_id')->nullable();
            // payroll_inputs.id or employee_salary_structure_lines.id; null for statutory
            $table->string('component_code')->nullable();
            // PF / ESIC / PT / TDS ... for statutory rows
            $table->string('label');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('reason');
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['payroll_run_id', 'source_type', 'source_id', 'component_code'],
                'prde_run_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_deduction_exceptions');
    }
};
