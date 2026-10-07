<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Services\MobileAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints for the employee mobile app. The app logs in once, stores the returned
 * device token, and sends it as a Bearer token from then on (see AuthenticateMobileDevice).
 */
class MobileAuthController extends Controller
{
    public function __construct(private readonly MobileAuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        [$token, $device] = $this->auth->login(
            $data['login'], $data['password'], $data['device_name'] ?? null, $data['platform'] ?? null,
            $data['app_version'] ?? null, $request->ip(), $request->userAgent(),
        );

        return response()->json([
            'token' => $token,
            'user' => Mobile\ProfileController::userSummary($device->user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => Mobile\ProfileController::userSummary($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->revoke($request->attributes->get('mobile_device'));

        return response()->json(['ok' => true]);
    }

    /** The app registers (or refreshes) this phone's Firebase push token after sign-in. */
    public function pushToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['nullable', 'string', 'max:512']]);

        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');

        // A token belongs to one phone install: detach it from any older sign-in on that phone.
        if (filled($data['token'] ?? null)) {
            MobileDevice::where('fcm_token', $data['token'])->whereKeyNot($device->id)->update(['fcm_token' => null]);
        }

        $device->forceFill(['fcm_token' => $data['token'] ?? null, 'fcm_token_updated_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
