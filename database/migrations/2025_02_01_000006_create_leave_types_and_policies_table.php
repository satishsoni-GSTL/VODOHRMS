<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('annual_entitlement', 5, 2)->default(0);
            $table->string('accrual_frequency')->default('annual');
            // monthly, annual, none
            $table->boolean('carry_forward_allowed')->default(false);
            $table->decimal('max_carry_forward', 5, 2)->nullable();
            $table->boolean('encashment_allowed')->default(false);
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('half_day_allowed')->default(true);
            $table->boolean('sandwich_rule_applicable')->default(false);
            $table->boolean('probation_allowed')->default(true);
            $table->decimal('min_days_per_request', 4, 2)->nullable();
            $table->decimal('max_days_per_request', 4, 2)->nullable();
            $table->boolean('attachment_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->json('applicable_to')->nullable();
            // scoping: employee_type_ids, grade_ids, department_ids
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
