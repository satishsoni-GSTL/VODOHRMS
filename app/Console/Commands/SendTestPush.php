<?php

namespace App\Console\Commands;

use App\Models\MobileDevice;
use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Console\Command;

/** Sends a test push notification to an employee's phone(s): php artisan push:test GS082 */
class SendTestPush extends Command
{
    protected $signature = 'push:test {employee : Employee code or e-mail of the person signed in to the app}';

    protected $description = 'Send a test push notification to an employee\'s phone';

    public function handle(FirebasePushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->error('Push is not configured. Run: php artisan push:setup');

            return self::FAILURE;
        }

        $id = $this->argument('employee');
        $user = User::where('employee_code', $id)->orWhere('email', $id)->first();

        if (! $user) {
            $this->error("No login found for \"$id\".");

            return self::FAILURE;
        }

        $devices = MobileDevice::active()->where('user_id', $user->id)->get();
        $withToken = $devices->whereNotNull('fcm_token')->count();
        $this->line("{$user->name}: {$devices->count()} signed-in phone(s), {$withToken} registered for push.");

        if ($withToken === 0) {
            $this->warn('Nothing to send to. The phone needs an app build that includes the Firebase keys, the user must be signed in, and notifications must be allowed.');

            return self::FAILURE;
        }

        $sent = $push->sendToUser($user, 'VODO HRMS test', 'Push notifications are working 🎉', ['screen' => 'home']);

        if ($sent === 0) {
            $this->error('Firebase refused the message — see storage/logs/laravel.log ("FCM push failed").');

            return self::FAILURE;
        }

        $this->info("✓ Sent to {$sent} phone(s). It should appear within a few seconds.");

        return self::SUCCESS;
    }
}
