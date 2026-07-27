<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipDownloadController extends Controller
{
    public function __invoke(Request $request, Payslip $payslip): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user->can('payroll.view') || $user->employee_id === $payslip->employee_id,
            403
        );

        abort_unless(Storage::disk('local')->exists($payslip->pdf_path), 404);

        return Storage::disk('local')->download($payslip->pdf_path, "payslip-{$payslip->payroll_month}.pdf");
    }
}
