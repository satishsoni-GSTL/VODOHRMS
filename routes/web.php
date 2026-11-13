<?php

use App\Http\Controllers\ExpenseReceiptDownloadController;
use App\Http\Controllers\ExpenseStatementDownloadController;
use App\Http\Controllers\Form16DownloadController;
use App\Http\Controllers\MyPayrollDownloadController;
use App\Http\Controllers\PayslipDownloadController;
use App\Http\Controllers\EmployeeDocumentDownloadController;
use App\Http\Controllers\PolicyDocumentDownloadController;
use App\Http\Controllers\PrejoiningController;
use App\Http\Controllers\ProbationLetterDownloadController;
use App\Http\Controllers\ReportDownloadController;
use App\Http\Controllers\SharedFileDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'loginUrl' => route('filament.admin.auth.login'),
        'logoExists' => file_exists(public_path('images/globalspace-logo.png')),
    ]);
});

// Public privacy policy — required for the Google Play listing and linked from the app.
Route::get('/privacy-policy', fn () => view('privacy-policy', [
    'updated' => '7 October 2026',
    'contact' => config('services.privacy.contact') ?: config('mail.from.address'),
]))->name('privacy-policy');

Route::get('/payslips/{payslip}/download', PayslipDownloadController::class)
    ->middleware('auth')
    ->name('payslips.download');

// Monthly expense statement: summary + all bills in one PDF (per employee), or a ZIP of all.
Route::get('/reports/expense-statement/{employee}', [ExpenseStatementDownloadController::class, 'employee'])
    ->middleware('auth')
    ->name('reports.expense-statement');
Route::get('/reports/expense-statements', [ExpenseStatementDownloadController::class, 'all'])
    ->middleware('auth')
    ->name('reports.expense-statements');

Route::get('/reports/{type}/download', ReportDownloadController::class)
    ->middleware('auth')
    ->name('reports.download');

Route::get('/my-payroll/download', MyPayrollDownloadController::class)
    ->middleware('auth')
    ->name('my-payroll.download');

Route::get('/policy-documents/{policyDocument}/download', PolicyDocumentDownloadController::class)
    ->middleware('auth')
    ->name('policy-documents.download');

Route::get('/expense-receipts/{expenseClaimLine}/download', ExpenseReceiptDownloadController::class)
    ->middleware('auth')
    ->name('expense-receipts.download');

Route::get('/form16/{form16}/download', Form16DownloadController::class)
    ->middleware('auth')
    ->name('form16.download');

// Pre-joining portal for new hires (no login: invite link + emailed one-time code).
Route::prefix('joining')->name('prejoining.')->middleware('throttle:prejoining')->group(function () {
    Route::get('{token}', [PrejoiningController::class, 'show'])->name('show');
    Route::post('{token}/send-code', [PrejoiningController::class, 'sendCode'])->middleware('throttle:prejoining-code')->name('send-code');
    Route::post('{token}/verify', [PrejoiningController::class, 'verify'])->middleware('throttle:prejoining-code')->name('verify');
    Route::post('{token}', [PrejoiningController::class, 'save'])->name('save');
    Route::post('{token}/documents/{document}/delete', [PrejoiningController::class, 'deleteDocument'])->name('documents.delete');
    Route::post('{token}/sign-out', [PrejoiningController::class, 'signOut'])->name('sign-out');
});

// Employee documents (HR, the employee themself, or their managers).
Route::get('/employee-documents/{document}/download', EmployeeDocumentDownloadController::class)
    ->middleware('auth')
    ->name('employee-documents.download');

Route::get('/probation-reviews/{review}/letter', ProbationLetterDownloadController::class)
    ->middleware('auth')
    ->name('probation-letters.download');

// Owner/HR download of a shared file from inside the portal (recipients use the share domain).
Route::get('/shared-files/{sharedFile}/download', SharedFileDownloadController::class)
    ->middleware('auth')
    ->name('shared-files.download');
