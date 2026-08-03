<?php

namespace App\Http\Middleware;

use App\Models\BiometricDevice;
use Closure;
use Illuminate\Http\Request;
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

        $device->update([
            'last_synced_at' => now(),
            'last_synced_ip' => $request->ip(),
        ]);

        $request->attributes->set('biometric_device', $device);

        return $next($request);
    }
}
