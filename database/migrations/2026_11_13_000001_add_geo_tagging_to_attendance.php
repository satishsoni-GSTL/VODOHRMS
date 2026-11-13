<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo-tagged attendance: every mobile punch records where it was made, and an office
 * Location can carry a geofence (centre + radius) that office staff must be inside to punch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('pincode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // Null = no geofence: office staff at this location cannot punch from the app.
            $table->unsignedInteger('geofence_radius_m')->nullable()->after('longitude');
        });

        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('source');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('accuracy_m', 8, 1)->nullable()->after('longitude');
            // Filled only for geofenced (office) punches.
            $table->foreignId('location_id')->nullable()->after('accuracy_m')->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('distance_m')->nullable()->after('location_id');
            $table->boolean('within_geofence')->nullable()->after('distance_m');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['latitude', 'longitude', 'accuracy_m', 'distance_m', 'within_geofence']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius_m']);
        });
    }
};
