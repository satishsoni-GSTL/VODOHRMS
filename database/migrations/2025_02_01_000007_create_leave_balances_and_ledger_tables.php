<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening_balance', 5, 2)->default(0);
            $table->decimal('credited', 5, 2)->default(0);
            $table->decimal('adjusted', 5, 2)->default(0);
            $table->decimal('used', 5, 2)->default(0);
            $table->decimal('lapsed', 5, 2)->default(0);
            $table->decimal('encashed', 5, 2)->default(0);
            $table->decimal('closing_balance', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('type');
            // opening, credit, debit, adjustment, lapse, encashment
            $table->decimal('days', 5, 2);
            $table->decimal('balance_after', 5, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_ledger_entries');
        Schema::dropIfExists('employee_leave_balances');
    }
};
