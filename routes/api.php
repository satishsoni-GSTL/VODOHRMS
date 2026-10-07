<?php

use App\Http\Controllers\Api\BiometricPunchController;
use App\Http\Controllers\Api\Mobile;
use App\Http\Controllers\Api\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.biometric-device', 'throttle:120,1'])->group(function () {
    Route::post('/biometric/punches', [BiometricPunchController::class, 'store']);
});

// Employee mobile app (one-time login; see App\Services\MobileAuthService).
Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth.mobile', 'throttle:120,1'])->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::post('/push-token', [MobileAuthController::class, 'pushToken']);

        Route::get('/dashboard', [Mobile\ProfileController::class, 'dashboard']);
        Route::get('/profile', [Mobile\ProfileController::class, 'show']);
        Route::post('/password', [Mobile\ProfileController::class, 'changePassword']);

        Route::get('/attendance', [Mobile\AttendanceController::class, 'month']);
        Route::post('/attendance/clock', [Mobile\AttendanceController::class, 'clock']);
        Route::get('/regularizations', [Mobile\AttendanceController::class, 'regularizations']);
        Route::post('/regularizations', [Mobile\AttendanceController::class, 'requestRegularization']);

        Route::get('/leave', [Mobile\LeaveController::class, 'index']);
        Route::post('/leave/preview', [Mobile\LeaveController::class, 'preview']);
        Route::post('/leave', [Mobile\LeaveController::class, 'store']);

        Route::get('/wfh', [Mobile\WorkFromHomeController::class, 'index']);
        Route::post('/wfh', [Mobile\WorkFromHomeController::class, 'store']);

        Route::get('/expenses', [Mobile\ExpenseController::class, 'index']);
        Route::post('/expenses', [Mobile\ExpenseController::class, 'store']);
        Route::get('/expenses/statement', [Mobile\ExpenseController::class, 'statement']);
        Route::get('/expenses/{claim}', [Mobile\ExpenseController::class, 'show']);
        Route::post('/expenses/{claim}/resubmit', [Mobile\ExpenseController::class, 'resubmit']);
        Route::get('/expense-lines/{line}/receipt', [Mobile\ExpenseController::class, 'receipt']);

        Route::get('/payslips', [Mobile\PayslipController::class, 'index']);
        Route::get('/payslips/{payslip}', [Mobile\PayslipController::class, 'show']);
        Route::get('/payslips/{payslip}/pdf', [Mobile\PayslipController::class, 'download']);

        Route::get('/holidays', [Mobile\HolidayController::class, 'index']);
        Route::post('/holidays/{holiday}/claim', [Mobile\HolidayController::class, 'claim']);
        Route::post('/optional-holiday-claims/{claim}/cancel', [Mobile\HolidayController::class, 'cancelClaim']);

        Route::get('/team', [Mobile\TeamController::class, 'index']);
        Route::get('/team/leave', [Mobile\TeamController::class, 'leave']);
        Route::get('/team/requests', [Mobile\TeamController::class, 'requests']);
        Route::get('/team/{employee}/attendance', [Mobile\TeamController::class, 'memberAttendance']);

        Route::get('/approvals', [Mobile\ApprovalController::class, 'index']);
        Route::post('/approvals/{instance}', [Mobile\ApprovalController::class, 'act']);

        Route::get('/policies', [Mobile\PolicyController::class, 'index']);
        Route::get('/policies/{policy}/download', [Mobile\PolicyController::class, 'download']);

        Route::get('/loans', [Mobile\LoanController::class, 'index']);
        Route::post('/loans', [Mobile\LoanController::class, 'store']);
    });
});
