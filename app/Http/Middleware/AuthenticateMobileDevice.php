<?php

namespace App\Http\Middleware;

use App\Services\MobileAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates employee mobile-app API calls by their device token (Bearer). The user is
 * set on the default guard so policies / `$user->can()` / ScopesToOwnTeam work exactly as
 * they do on the web. A revoked device, inactive login or a login without an employee
 * record gets 401, which the app treats as "signed out".
 */
class AuthenticateMobileDevice
{
    public function __construct(private readonly MobileAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->auth->deviceForToken($request->bearerToken(), $request->ip());

        if (! $device || ! $device->user->employee_id) {
            return response()->json(['message' => 'Signed out. Please log in again.'], 401);
        }

        Auth::setUser($device->user);
        $request->setUserResolver(fn () => $device->user);
        $request->attributes->set('mobile_device', $device);

        return $next($request);
    }
}
