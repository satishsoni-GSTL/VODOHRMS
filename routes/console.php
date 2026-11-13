<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hr:send-daily-reminders')->dailyAt('08:00');
// Personal birthday / work-anniversary wish pushed to the celebrant's phone.
Schedule::command('hr:send-celebration-wishes')->dailyAt('09:00');
// Probation ending in 30/15/7/0 days (then weekly while overdue) -> manager + HR.
Schedule::command('hr:send-probation-reminders')->dailyAt('08:15');
// Onboarding tasks that went overdue yesterday (Mondays: all overdue) -> their owners.
Schedule::command('hr:send-onboarding-reminders')->dailyAt('08:30');
