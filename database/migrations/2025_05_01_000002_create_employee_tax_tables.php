<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tax_regimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->string('selected_regime');
            // old, new
            $table->date('selection_date');
            $table->date('lock_date')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'financial_year_id']);
        });

        Schema::create('employee_tax_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->foreignId('tax_section_id')->constrained('tax_sections')->cascadeOnDelete();
            $table->decimal('declared_amount', 12, 2);
            $table->string('proof_path')->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('rejected_amount', 12, 2)->nullable();
            $table->decimal('eligible_amount', 12, 2)->nullable();
            $table->text('hr_remarks')->nullable();
            $table->string('status')->default('declared');
            // declared, proof_submitted, verified, rejected
            $table->timestamps();

            $table->unique(['employee_id', 'financial_year_id', 'tax_section_id'], 'emp_tax_decl_unique');
        });

        Schema::create('employee_tax_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->string('regime');
            $table->decimal('projected_annual_income', 12, 2)->default(0);
            $table->decimal('total_exemptions', 12, 2)->default(0);
            $table->decimal('taxable_income', 12, 2)->default(0);
            $table->decimal('tax_before_rebate', 12, 2)->default(0);
            $table->decimal('rebate', 12, 2)->default(0);
            $table->decimal('surcharge', 12, 2)->default(0);
            $table->decimal('cess', 12, 2)->default(0);
            $table->decimal('final_tax', 12, 2)->default(0);
            $table->decimal('tds_deducted_till_date', 12, 2)->default(0);
            $table->decimal('remaining_tax', 12, 2)->default(0);
            $table->decimal('projected_monthly_tds', 12, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'financial_year_id', 'regime'], 'emp_tax_proj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tax_projections');
        Schema::dropIfExists('employee_tax_declarations');
        Schema::dropIfExists('employee_tax_regimes');
    }
};
