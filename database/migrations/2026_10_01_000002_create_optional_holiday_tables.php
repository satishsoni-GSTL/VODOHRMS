<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optional_holiday_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            // calendar year of the holiday date
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            // null = default limit for everyone that year; set = that employee's own limit
            $table->unsignedTinyInteger('max_claims');
            $table->timestamps();

            $table->unique(['year', 'employee_id'], 'opt_holiday_limit_unique');
        });

        Schema::create('optional_holiday_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('holiday_id')->constrained('holidays')->cascadeOnDelete();
            $table->string('status')->default('claimed');
            // claimed, cancelled
            $table->text('reason')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'holiday_id'], 'opt_holiday_claim_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optional_holiday_claims');
        Schema::dropIfExists('optional_holiday_limits');
    }
};
