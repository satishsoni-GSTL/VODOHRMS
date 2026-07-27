<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('request_type');
            // missing_punch, wrong_in, wrong_out, wfh, on_duty, client_visit, other
            $table->json('old_values')->nullable();
            $table->json('requested_values');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending');
            // pending, manager_approved, hr_approved, approved, rejected, sent_back
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularizations');
    }
};
