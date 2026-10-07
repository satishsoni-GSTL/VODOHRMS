<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            // Firebase Cloud Messaging registration token for push notifications to this phone.
            $table->string('fcm_token', 512)->nullable()->after('app_version');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'fcm_token_updated_at']);
        });
    }
};
