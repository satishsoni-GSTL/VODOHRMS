<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            // sha256 of the device token; the plain token only ever lives on the phone
            $table->string('device_name')->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
