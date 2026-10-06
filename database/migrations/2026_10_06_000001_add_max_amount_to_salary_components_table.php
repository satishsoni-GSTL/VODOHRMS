<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->decimal('max_amount', 12, 2)->nullable()->after('default_amount');
        });

        // New PF rule: 12% of Basic, capped at ₹3,000/month, for both employee and employer.
        DB::table('salary_components')
            ->whereIn('code', ['PF_EMPLOYEE', 'PF_EMPLOYER'])
            ->update([
                'calculation_type' => 'percentage',
                'default_percentage' => 12,
                'max_amount' => 3000,
            ]);
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn('max_amount');
        });
    }
};
