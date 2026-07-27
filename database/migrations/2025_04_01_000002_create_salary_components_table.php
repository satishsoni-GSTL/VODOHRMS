<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type');
            // earning, deduction, employer_contribution
            $table->string('calculation_type')->default('fixed');
            // fixed, percentage, formula
            $table->foreignId('percentage_of_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            $table->decimal('default_percentage', 5, 2)->nullable();
            $table->decimal('default_amount', 12, 2)->nullable();
            // used when calculation_type=fixed for auto-computed deduction/employer_contribution
            // components (e.g. a flat Professional Tax slab) rather than HR-entered earning lines
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pf_applicable')->default(false);
            $table->boolean('is_esic_applicable')->default(false);
            $table->boolean('is_prorated')->default(true);
            $table->boolean('is_ctc_component')->default(true);
            $table->boolean('is_gross_component')->default(true);
            $table->boolean('show_on_payslip')->default(true);
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
