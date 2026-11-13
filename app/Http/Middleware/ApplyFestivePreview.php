<?php

namespace App\Http\Middleware;

use App\Support\FestiveThemes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ?festive_preview=<key> shows a celebration theme in this browser session only (for HR to
 * check how it looks before switching it on); ?festive_preview=off ends the preview.
 * Purely cosmetic, so it is not permission-gated — the login page can be previewed too.
 */
class ApplyFestivePreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->query('festive_preview');

        if (is_string($key) && $request->hasSession()) {
            if ($key === 'off') {
                $request->session()->forget(FestiveThemes::SESSION_PREVIEW);
            } elseif (FestiveThemes::definition($key)) {
                $request->session()->put(FestiveThemes::SESSION_PREVIEW, $key);
            }

            FestiveThemes::flush();
        }

        return $next($request);
    }
}
