<?php

use App\Http\Controllers\MyPayrollDownloadController;
use App\Http\Controllers\PayslipDownloadController;
use App\Http\Controllers\PolicyDocumentDownloadController;
use App\Http\Controllers\ReportDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'loginUrl' => route('filament.admin.auth.login'),
        'logoExists' => file_exists(public_path('images/globalspace-logo.png')),
    ]);
});

Route::get('/payslips/{payslip}/download', PayslipDownloadController::class)
    ->middleware('auth')
    ->name('payslips.download');

Route::get('/reports/{type}/download', ReportDownloadController::class)
    ->middleware('auth')
    ->name('reports.download');

Route::get('/my-payroll/download', MyPayrollDownloadController::class)
    ->middleware('auth')
    ->name('my-payroll.download');

Route::get('/policy-documents/{policyDocument}/download', PolicyDocumentDownloadController::class)
    ->middleware('auth')
    ->name('policy-documents.download');
