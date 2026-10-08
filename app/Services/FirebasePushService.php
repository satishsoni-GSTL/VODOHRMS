<?php

namespace App\Services;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends push notifications to the employee mobile app through Firebase Cloud Messaging
 * (HTTP v1 API), authenticated with a Firebase service-account key:
 *
 *   FIREBASE_CREDENTIALS=/absolute/path/to/firebase-service-account.json
 *
 * Without credentials this is a no-op, so the HRMS works unchanged until push is set up.
 * Delivery is best-effort: failures are logged and never break the business action that
 * triggered the notification. Tokens Firebase reports as dead are cleared.
 */
class FirebasePushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  array<string, scalar|null>  $data  e.g. ['screen' => 'approvals']
     * @return int  number of phones the message was delivered to
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $devices = MobileDevice::query()->active()->where('user_id', $user->id)->whereNotNull('fcm_token')->get();
        $sent = 0;

        foreach ($devices as $device) {
            if ($this->sendToDevice($device, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function sendToDevice(MobileDevice $device, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials();

        if (! $credentials || ! $device->fcm_token) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken($credentials))
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                    'message' => [
                        'token' => $device->fcm_token,
                        'notification' => ['title' => $title, 'body' => $body],
                        // FCM data values must be strings.
                        'data' => array_map(fn ($v) => (string) $v, array_filter($data, fn ($v) => $v !== null)),
                        'android' => [
                            'priority' => 'high',
                            'notification' => ['channel_id' => 'hrms_default', 'sound' => 'default'],
                        ],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            // The app was uninstalled or the token rotated: stop sending to it.
            $errorCode = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true) || $response->status() === 404) {
                $device->forceFill(['fcm_token' => null, 'fcm_token_updated_at' => now()])->save();
            }

            Log::warning('FCM push failed', ['device' => $device->id, 'status' => $response->status(), 'error' => $errorCode]);
        } catch (Throwable $e) {
            Log::warning('FCM push failed', ['device' => $device->id, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Proves the service-account key works by getting a real OAuth token from Google.
     * Returns the Firebase project id; throws with Google's error otherwise.
     */
    public function verifyCredentials(): string
    {
        $credentials = $this->credentials();

        if (! $credentials) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS is not set, or the file is missing / not a service-account key.');
        }

        Cache::forget('fcm_access_token_'.md5($credentials['client_email']));
        $this->accessToken($credentials);

        return $credentials['project_id'];
    }

    /**
     * OAuth2 access token for FCM, from a self-signed service-account JWT (RS256).
     * Cached for 50 minutes (Google issues 60-minute tokens).
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token_'.md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->timeout(10)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw();

            return $response->json('access_token');
        });
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri?: string}|null */
    private function credentials(): ?array
    {
        $path = config('services.firebase.credentials');

        if (! $path || ! is_readable($path)) {
            return null;
        }

        return once(function () use ($path) {
            $json = json_decode((string) file_get_contents($path), true);

            return is_array($json) && isset($json['project_id'], $json['client_email'], $json['private_key']) ? $json : null;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
