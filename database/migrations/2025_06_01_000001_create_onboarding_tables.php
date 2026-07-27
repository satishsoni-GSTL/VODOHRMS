<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->boolean('personal_details_done')->default(false);
            $table->boolean('documents_done')->default(false);
            $table->boolean('statutory_done')->default(false);
            $table->boolean('bank_done')->default(false);
            $table->boolean('department_done')->default(false);
            $table->boolean('salary_done')->default(false);
            $table->boolean('login_done')->default(false);
            $table->boolean('asset_allocation_done')->default(false);
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('asset_type');
            $table->string('asset_tag')->nullable();
            $table->date('allocated_on');
            $table->date('returned_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assets');
        Schema::dropIfExists('onboarding_checklists');
    }
};
