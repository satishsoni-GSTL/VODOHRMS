<?php

use App\Http\Controllers\FileShareController;
use Illuminate\Support\Facades\Route;

/*
| Recipient-facing share pages. Bound to config('fileshare.domain') when FILESHARE_URL is
| set (production: file.globalspace.in); otherwise served on APP_URL for local dev.
*/

$routes = Route::middleware(['web', 'throttle:fileshare'])->prefix('s')->name('fileshare.');

if ($domain = config('fileshare.domain')) {
    $routes->domain($domain);
}

$routes->group(function () {
    Route::get('{token}', [FileShareController::class, 'show'])->name('show');
    Route::post('{token}/send-code', [FileShareController::class, 'sendCode'])->middleware('throttle:fileshare-code')->name('send-code');
    Route::post('{token}/verify', [FileShareController::class, 'verify'])->middleware('throttle:fileshare-code')->name('verify');
    Route::get('{token}/preview', [FileShareController::class, 'preview'])->name('preview');
    Route::get('{token}/download', [FileShareController::class, 'download'])->name('download');
    Route::post('{token}/comments', [FileShareController::class, 'comment'])->middleware('throttle:fileshare-comment')->name('comment');
    Route::post('{token}/sign-out', [FileShareController::class, 'signOut'])->name('sign-out');
});
