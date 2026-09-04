<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $table->decimal('lop_amount', 12, 2)->default(0)->after('lop_days');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $table->dropColumn('lop_amount');
        });
    }
};
