<?php

namespace App\Console\Commands;

use App\Services\FirebasePushService;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-step Firebase push setup from the two files downloaded from the Firebase console:
 *
 *   firebase-keys/google-services.json     (Project settings → Your apps → Android app)
 *   firebase-keys/service-account.json     (Project settings → Service accounts → Generate new private key)
 *
 * It points the server at the service-account key (FIREBASE_CREDENTIALS in .env), checks the
 * key works with Google, and writes the app's Firebase keys into
 * mobile-app/release-config.json for the next release build.
 */
class SetupPushNotifications extends Command
{
    protected $signature = 'push:setup
        {--dir=firebase-keys : Folder holding google-services.json and service-account.json}
        {--package=com.globalspace.vodo_hrms : Android package name registered in Firebase}';

    protected $description = 'Configure Firebase push notifications for the employee mobile app';

    public function handle(FirebasePushService $push): int
    {
        $dir = base_path($this->option('dir'));
        $googleServices = "$dir/google-services.json";
        $serviceAccount = "$dir/service-account.json";

        foreach ([$googleServices, $serviceAccount] as $file) {
            if (! is_readable($file)) {
                $this->error("Missing: $file");
                $this->line('Download both files from the Firebase console (see mobile-app/README.md → Push notifications).');

                return self::FAILURE;
            }
        }

        // ---- app keys from google-services.json ------------------------------------------
        $gs = json_decode((string) file_get_contents($googleServices), true);
        $package = $this->option('package');
        $client = collect($gs['client'] ?? [])->first(fn ($c) => ($c['client_info']['android_client_info']['package_name'] ?? null) === $package);

        if (! $client) {
            $this->error("google-services.json has no Android app with package \"$package\". In Firebase, add an Android app with exactly that package name and download the file again.");

            return self::FAILURE;
        }

        $app = [
            'FIREBASE_API_KEY' => $client['api_key'][0]['current_key'] ?? '',
            'FIREBASE_PROJECT_ID' => $gs['project_info']['project_id'] ?? '',
            'FIREBASE_SENDER_ID' => $gs['project_info']['project_number'] ?? '',
            'FIREBASE_ANDROID_APP_ID' => $client['client_info']['mobilesdk_app_id'] ?? '',
        ];

        if (in_array('', $app, true)) {
            $this->error('google-services.json is incomplete: '.json_encode($app));

            return self::FAILURE;
        }

        // ---- server key ------------------------------------------------------------------
        $sa = json_decode((string) file_get_contents($serviceAccount), true);
        if (($sa['type'] ?? null) !== 'service_account' || empty($sa['private_key'])) {
            $this->error('service-account.json is not a Firebase service-account key (Project settings → Service accounts → Generate new private key).');

            return self::FAILURE;
        }
        if (($sa['project_id'] ?? null) !== $app['FIREBASE_PROJECT_ID']) {
            $this->error("The two files are from different Firebase projects ({$sa['project_id']} vs {$app['FIREBASE_PROJECT_ID']}).");

            return self::FAILURE;
        }

        config(['services.firebase.credentials' => $serviceAccount]);

        try {
            $project = $push->verifyCredentials();
            $this->info("✓ Server key works — connected to Firebase project \"$project\".");
        } catch (Throwable $e) {
            $this->error('Google rejected the service-account key: '.$e->getMessage());

            return self::FAILURE;
        }

        // Only now that Google has accepted the key.
        $this->setEnv('FIREBASE_CREDENTIALS', $serviceAccount);

        // ---- app build config ------------------------------------------------------------
        $configFile = base_path('mobile-app/release-config.json');
        $config = is_readable($configFile) ? (json_decode((string) file_get_contents($configFile), true) ?: []) : [];
        $config = array_merge(['HRMS_URL' => '', 'ALLOW_SERVER_CHANGE' => 'false'], $config, $app);
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->info('✓ App keys written to mobile-app/release-config.json');

        $this->newLine();
        $this->line('Next:');
        $this->line('  1. Rebuild the app: bump "version:" in mobile-app/pubspec.yaml, then run mobile-app/build-release.ps1');
        $this->line('  2. On the live server: copy firebase-keys/service-account.json there, set FIREBASE_CREDENTIALS to its path, php artisan config:cache');
        $this->line('  3. Install the new build, sign in, then: php artisan push:test <employee code>');

        return self::SUCCESS;
    }

    private function setEnv(string $key, string $value): void
    {
        $path = base_path('.env');
        $env = (string) file_get_contents($path);
        $line = $key.'="'.str_replace('\\', '/', $value).'"';

        $env = preg_match("/^{$key}=.*$/m", $env)
            ? preg_replace("/^{$key}=.*$/m", $line, $env)
            : rtrim($env).PHP_EOL.PHP_EOL.'# Firebase push notifications (php artisan push:setup)'.PHP_EOL.$line.PHP_EOL;

        file_put_contents($path, $env);
        $this->info("✓ $key set in .env");
    }
}
