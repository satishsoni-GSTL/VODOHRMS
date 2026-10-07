<?php

namespace App\Http\Middleware;

use App\Models\BiometricDevice;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBiometricDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $device = $token ? BiometricDevice::findByToken($token) : null;

        if (! $device) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // A valid token from outside the device's allowed network is treated as a stolen
        // token: refuse it and leave a trace for HR / IT.
        if (! $device->allowsIp($request->ip())) {
            Log::warning('Biometric punch API: token used from a non-allowed IP', ['device' => $device->id, 'ip' => $request->ip()]);
            app(AuditLogService::class)->log(
                'tampering_blocked', $device, [], ['ip' => $request->ip()],
                reason: "Biometric device token used from {$request->ip()}, which is not in the device's allowed IPs.",
                module: 'security',
            );

            return response()->json(['message' => 'This device is not allowed to sync from this network.'], 403);
        }

        $device->update([
            'last_synced_at' => now(),
            'last_synced_ip' => $request->ip(),
        ]);

        $request->attributes->set('biometric_device', $device);

        return $next($request);
    }
}
