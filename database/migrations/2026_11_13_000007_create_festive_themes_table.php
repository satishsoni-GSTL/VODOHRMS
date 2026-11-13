<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Celebration themes HR switches on from Organization → Celebration Themes. The look of
 * each theme is in code (App\Support\FestiveThemes); this table holds on/off, dates,
 * wording and countdown days. Diwali carries over the dates that were in config/festive.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festive_themes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('title')->nullable();      // null = the theme's default greeting
            $table->text('message')->nullable();
            $table->json('days')->nullable();          // [{name, date}] for the countdown
            $table->string('main_day', 60)->nullable();
            $table->string('venue')->nullable();       // Annual Day
            $table->timestamps();
        });

        $now = now();
        $days = fn (array $pairs) => json_encode(array_map(fn ($name, $date) => ['name' => $name, 'date' => $date], array_keys($pairs), $pairs));

        $diwaliDays = config('festive.diwali.days', []);

        $rows = [
            // Same window as before; the dates for next year can now be changed from the panel.
            ['key' => 'diwali', 'is_enabled' => (bool) config('festive.diwali.enabled', true),
                'starts_on' => config('festive.diwali.from'), 'ends_on' => config('festive.diwali.until'),
                'days' => $diwaliDays ? $days($diwaliDays) : null, 'main_day' => 'Diwali'],
            ['key' => 'christmas', 'starts_on' => '2026-12-20', 'ends_on' => '2026-12-26', 'days' => $days(['Christmas' => '2026-12-25'])],
            ['key' => 'new_year', 'starts_on' => '2026-12-29', 'ends_on' => '2027-01-02', 'days' => $days(["New Year's Eve" => '2026-12-31', "New Year's Day" => '2027-01-01'])],
            ['key' => 'republic_day', 'starts_on' => '2027-01-24', 'ends_on' => '2027-01-27', 'days' => $days(['Republic Day' => '2027-01-26'])],
            ['key' => 'holi', 'starts_on' => '2027-03-19', 'ends_on' => '2027-03-23', 'days' => $days(['Holika Dahan' => '2027-03-21', 'Holi' => '2027-03-22'])],
            ['key' => 'independence_day', 'starts_on' => '2027-08-13', 'ends_on' => '2027-08-16', 'days' => $days(['Independence Day' => '2027-08-15'])],
            ['key' => 'annual_day'],
            ['key' => 'birthday'],
        ];

        foreach ($rows as $row) {
            DB::table('festive_themes')->insert($row + [
                'is_enabled' => false,
                'starts_on' => null,
                'ends_on' => null,
                'days' => null,
                'main_day' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('festive_themes');
    }
};
