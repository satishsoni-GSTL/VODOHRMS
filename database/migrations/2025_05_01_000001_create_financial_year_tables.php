<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // e.g. "2026-27"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('assessment_year');
            // e.g. "2027-28"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_regime_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->string('regime');
            // old, new
            $table->decimal('income_from', 12, 2);
            $table->decimal('income_to', 12, 2)->nullable();
            // null = no upper bound
            $table->decimal('tax_percent', 5, 2);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['financial_year_id', 'regime']);
        });

        Schema::create('tax_regime_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->string('regime');
            $table->decimal('standard_deduction', 12, 2)->default(0);
            $table->decimal('rebate_limit_income', 12, 2)->nullable();
            // taxable income at/below which rebate applies (Sec 87A style)
            $table->decimal('rebate_max_amount', 12, 2)->nullable();
            $table->json('surcharge_rules')->nullable();
            // [{"above_income": 5000000, "percent": 10}, ...]
            $table->decimal('cess_percent', 5, 2)->default(4);
            $table->boolean('regime_change_allowed')->default(true);
            $table->timestamps();

            $table->unique(['financial_year_id', 'regime']);
        });

        Schema::create('tax_sections', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            // 80C, 80D, HRA, HOME_LOAN_INTEREST, NPS, ...
            $table->string('name');
            $table->decimal('max_limit', 12, 2)->nullable();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['financial_year_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_sections');
        Schema::dropIfExists('tax_regime_configs');
        Schema::dropIfExists('tax_regime_slabs');
        Schema::dropIfExists('financial_years');
    }
};
