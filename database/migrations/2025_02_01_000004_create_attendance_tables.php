<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('first_in')->nullable();
            $table->time('last_out')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->decimal('effective_hours', 5, 2)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_going_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status')->default('present');
            // present, absent, half_day, wfh, on_duty, weekly_off, holiday, leave, missing_punch
            $table->string('source')->default('manual');
            // biometric, manual, import, self
            $table->text('remarks')->nullable();
            $table->boolean('is_frozen')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->dateTime('punch_time');
            $table->string('punch_type');
            // in, out
            $table->string('source')->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendances');
    }
};
