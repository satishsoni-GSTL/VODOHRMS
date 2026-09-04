<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deduction waiver is per-employee. Rebuild the uniqueness guard to include employee_id
 * so one employee's exception can never collide with — or be read as — another's.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add the new index first so the payroll_run_id foreign key still has a covering
        // index (both start with payroll_run_id), then drop the old one.
        Schema::table('payroll_run_deduction_exceptions', function (Blueprint $table) {
            $table->unique(
                ['payroll_run_id', 'employee_id', 'source_type', 'source_id', 'component_code'],
                'prde_run_emp_source_unique'
            );
        });

        Schema::table('payroll_run_deduction_exceptions', function (Blueprint $table) {
            $table->dropUnique('prde_run_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_deduction_exceptions', function (Blueprint $table) {
            $table->unique(
                ['payroll_run_id', 'source_type', 'source_id', 'component_code'],
                'prde_run_source_unique'
            );
        });

        Schema::table('payroll_run_deduction_exceptions', function (Blueprint $table) {
            $table->dropUnique('prde_run_emp_source_unique');
        });
    }
};
