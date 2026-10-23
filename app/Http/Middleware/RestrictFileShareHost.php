<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The file-share domain (e.g. file.globalspace.in) runs on this same app but must only
 * expose the share pages — never the HRMS login, admin panel or APIs. The share routes
 * themselves are domain-bound, so they 404 on the main HRMS host.
 */
class RestrictFileShareHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = config('fileshare.domain');

        if ($domain && strcasecmp($request->getHost(), $domain) === 0 && ! $request->is('s/*', 'up')) {
            abort(404);
        }

        return $next($request);
    }
}
