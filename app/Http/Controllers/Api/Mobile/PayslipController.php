<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Payslip;
use App\Models\PayrollRunEmployeeLine;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My salary slips — the same payslips the web "My Salary Slips" page lists (generated once
 * a payroll run is finalized), with the earnings/deductions breakdown and the PDF.
 */
class PayslipController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $items = Payslip::query()
            ->with('payrollRunEmployee')
            ->where('employee_id', $this->employee($request)->id)
            ->orderByDesc('payroll_month')
            ->get()
            ->map(fn (Payslip $p) => [
                'id' => $p->id,
                'payroll_month' => $p->payroll_month,
                'gross' => (float) ($p->payrollRunEmployee?->gross_earnings ?? 0),
                'deductions' => (float) ($p->payrollRunEmployee?->total_deductions ?? 0),
                'net_pay' => (float) ($p->payrollRunEmployee?->net_pay ?? 0),
                'paid_days' => (float) ($p->payrollRunEmployee?->paid_days ?? 0),
                'lop_days' => (float) ($p->payrollRunEmployee?->lop_days ?? 0),
                'generated_at' => $p->generated_at?->toIso8601String(),
            ]);

        return response()->json(['items' => $items]);
    }

    public function show(Request $request, Payslip $payslip): JsonResponse
    {
        abort_unless($payslip->employee_id === $this->employee($request)->id, 404);

        $run = $payslip->payrollRunEmployee()->with('lines')->first();
        $line = fn (PayrollRunEmployeeLine $l) => ['label' => $l->label, 'amount' => (float) $l->amount];

        return response()->json([
            'id' => $payslip->id,
            'payroll_month' => $payslip->payroll_month,
            'paid_days' => (float) ($run?->paid_days ?? 0),
            'lop_days' => (float) ($run?->lop_days ?? 0),
            'lop_amount' => (float) ($run?->lop_amount ?? 0),
            'earnings' => $run ? $run->lines->where('component_type', SalaryComponent::TYPE_EARNING)->map($line)->values() : [],
            'deductions' => $run ? $run->lines->where('component_type', SalaryComponent::TYPE_DEDUCTION)->map($line)->values() : [],
            'gross' => (float) ($run?->gross_earnings ?? 0),
            'total_deductions' => (float) ($run?->total_deductions ?? 0),
            'net_pay' => (float) ($run?->net_pay ?? 0),
            'has_pdf' => $payslip->pdf_path && Storage::disk(self::DISK)->exists($payslip->pdf_path),
        ]);
    }

    public function download(Request $request, Payslip $payslip): StreamedResponse
    {
        abort_unless($payslip->employee_id === $this->employee($request)->id, 404);
        abort_unless($payslip->pdf_path && Storage::disk(self::DISK)->exists($payslip->pdf_path), 404);

        return Storage::disk(self::DISK)->download($payslip->pdf_path, "payslip-{$payslip->payroll_month}.pdf");
    }
}
