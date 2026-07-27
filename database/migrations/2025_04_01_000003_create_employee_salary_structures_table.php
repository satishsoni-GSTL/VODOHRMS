<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // never overwritten — a new revision inserts a new row and closes the old one's effective_to
            $table->decimal('annual_ctc', 12, 2);
            $table->decimal('monthly_gross', 12, 2);
            $table->decimal('previous_ctc', 12, 2)->nullable();
            $table->decimal('revised_ctc', 12, 2)->nullable();
            $table->decimal('increment_percent', 6, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('employee_salary_structure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('employee_salary_structures')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('monthly_amount', 12, 2);
            $table->decimal('annual_amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structure_lines');
        Schema::dropIfExists('employee_salary_structures');
    }
};
