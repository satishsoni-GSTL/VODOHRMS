<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repurposes the unused `password_reset_tokens` table (email is already its primary
     * key) for the self-service OTP forgot-password flow: `token` holds Hash::make($otp)
     * instead of a Laravel broker token, `attempts` counts failed verifications, and
     * `expires_at` bounds the OTP's lifetime. See App\Filament\Pages\Auth\ForgotPassword /
     * ResetPasswordWithOtp.
     */
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('token');
            $table->timestamp('expires_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'expires_at']);
        });
    }
};
