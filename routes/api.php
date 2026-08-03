<?php

use App\Http\Controllers\Api\BiometricPunchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.biometric-device', 'throttle:120,1'])->group(function () {
    Route::post('/biometric/punches', [BiometricPunchController::class, 'store']);
});
