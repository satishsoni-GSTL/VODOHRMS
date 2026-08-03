<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_punch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->string('device_user_id');
            $table->dateTime('punch_time');
            $table->string('punch_type')->nullable();
            $table->json('raw_payload')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, matched, unmatched, duplicate
            $table->foreignId('attendance_punch_id')->nullable()->constrained('attendance_punches')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['biometric_device_id', 'device_user_id', 'punch_time'], 'device_punch_logs_unique_punch');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_punch_logs');
    }
};
