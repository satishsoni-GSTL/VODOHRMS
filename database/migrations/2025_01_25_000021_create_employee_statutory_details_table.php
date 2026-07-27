<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_statutory_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->text('pan')->nullable();
            $table->text('aadhaar')->nullable();
            $table->string('uan')->nullable();
            $table->string('pf_number')->nullable();
            $table->string('esic_number')->nullable();
            $table->boolean('professional_tax_applicable')->default(true);
            $table->string('tax_regime_default')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_statutory_details');
    }
};
