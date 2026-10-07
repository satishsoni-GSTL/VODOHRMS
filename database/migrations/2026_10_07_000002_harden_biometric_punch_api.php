<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            // Comma-separated IPs / CIDR ranges the sync agent may post from (the office's
            // public IP). Empty = any IP (not recommended).
            $table->text('allowed_ips')->nullable()->after('location');
        });

        Schema::table('device_punch_logs', function (Blueprint $table) {
            // Where each punch was posted from — to spot punches sent from outside the office.
            $table->string('source_ip', 45)->nullable()->after('raw_payload');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', fn (Blueprint $table) => $table->dropColumn('allowed_ips'));
        Schema::table('device_punch_logs', fn (Blueprint $table) => $table->dropColumn('source_ip'));
    }
};
