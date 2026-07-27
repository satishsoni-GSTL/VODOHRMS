<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('requires_bill')->default(true);
            $table->boolean('requires_project')->default(false);
            $table->string('gl_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('claim_date');
            $table->string('project_client')->nullable();
            $table->string('status')->default('draft');
            // draft, submitted, manager_approved, pending_finance, approved, rejected, paid
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->decimal('total_requested_amount', 12, 2)->default(0);
            $table->decimal('total_approved_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('expense_claim_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->constrained('expense_claims')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->date('expense_date');
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('bill_number')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->unique()->constrained('expense_claims')->cascadeOnDelete();
            // unique() guards against double payment at the DB level
            $table->decimal('paid_amount', 12, 2);
            $table->date('paid_on');
            $table->string('payment_reference')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expense_claim_lines');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('expense_categories');
    }
};
