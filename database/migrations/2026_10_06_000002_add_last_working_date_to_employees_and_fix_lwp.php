<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('last_working_date')->nullable()->after('confirmation_date');
        });

        // Carry over last working dates already approved on resignations.
        DB::table('resignations')
            ->where('status', 'hr_approved')
            ->whereNotNull('approved_last_working_date')
            ->orderBy('id')
            ->get(['employee_id', 'approved_last_working_date'])
            ->each(fn ($r) => DB::table('employees')
                ->where('id', $r->employee_id)
                ->update(['last_working_date' => $r->approved_last_working_date]));

        // Leave Without Pay must never be a paid leave.
        DB::table('leave_types')->where('code', 'LWP')->update(['is_paid_leave' => false]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('last_working_date');
        });
    }
};
