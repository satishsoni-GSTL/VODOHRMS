<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_employee_id')->unique()->constrained('payroll_run_employees')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('payroll_month', 7);
            $table->string('pdf_path');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['employee_id', 'payroll_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
