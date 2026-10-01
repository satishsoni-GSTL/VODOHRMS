<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tds_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->string('payroll_month', 7);
            // Y-m
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            // true once HR has edited the generated amount — kept on regenerate unless overwritten
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'financial_year_id', 'payroll_month'], 'emp_tds_sched_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tds_schedules');
    }
};
