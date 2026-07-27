<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 2);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_session')->nullable();
            // first_half, second_half
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('draft');
            // draft, pending, approved, rejected, sent_back, cancelled
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
