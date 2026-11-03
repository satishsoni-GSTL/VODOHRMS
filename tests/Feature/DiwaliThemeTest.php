<?php

namespace Tests\Feature;

use App\Support\DiwaliTheme;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiwaliThemeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_only_within_the_configured_window(): void
    {
        config(['festive.diwali.enabled' => true, 'festive.diwali.from' => '2026-11-01', 'festive.diwali.until' => '2026-11-12']);

        Carbon::setTestNow('2026-10-31 23:59:59');
        $this->assertFalse(DiwaliTheme::isActive());

        Carbon::setTestNow('2026-11-01 00:00:00');
        $this->assertTrue(DiwaliTheme::isActive());

        Carbon::setTestNow('2026-11-12 23:59:59');
        $this->assertTrue(DiwaliTheme::isActive());

        Carbon::setTestNow('2026-11-13 00:00:00');
        $this->assertFalse(DiwaliTheme::isActive());
    }

    public function test_countdown_follows_the_festival_days(): void
    {
        config(['festive.diwali.days' => [
            'Dhanteras' => '2026-11-06',
            'Choti Diwali' => '2026-11-07',
            'Diwali' => '2026-11-08',
            'Govardhan' => '2026-11-10',
            'Bhai Dooj' => '2026-11-11',
        ]]);

        foreach ([
            '2026-11-03 18:00:00' => '5 days to Diwali',
            '2026-11-06 09:00:00' => 'Today is Dhanteras',
            '2026-11-08 23:00:00' => 'Today is Diwali',
            '2026-11-09 09:00:00' => 'Govardhan is tomorrow',
            '2026-11-11 09:00:00' => 'Today is Bhai Dooj',
            '2026-11-12 09:00:00' => 'Thank you for celebrating with us',
        ] as $now => $expected) {
            Carbon::setTestNow($now);
            $this->assertSame($expected, DiwaliTheme::countdown(), $now);
        }
    }

    public function test_can_be_switched_off(): void
    {
        config(['festive.diwali.enabled' => false, 'festive.diwali.from' => '2026-11-01', 'festive.diwali.until' => '2026-11-12']);
        Carbon::setTestNow('2026-11-08 12:00:00');

        $this->assertFalse(DiwaliTheme::isActive());
    }

    public function test_login_page_shows_the_theme_during_the_festival(): void
    {
        config(['festive.diwali.enabled' => true, 'festive.diwali.from' => '2026-11-01', 'festive.diwali.until' => '2026-11-12']);
        Carbon::setTestNow('2026-11-08 12:00:00');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('css/festive/diwali.css')
            ->assertSee('data-dw-stage', false)
            ->assertSee('dw-greeting', false);
    }

    public function test_login_page_is_unchanged_outside_the_festival(): void
    {
        config(['festive.diwali.enabled' => true, 'festive.diwali.from' => '2026-11-01', 'festive.diwali.until' => '2026-11-12']);
        Carbon::setTestNow('2026-12-01 12:00:00');

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('css/festive/diwali.css')
            ->assertDontSee('data-dw-stage', false);
    }
}
