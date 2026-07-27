<?php

namespace App\Services;

use App\Models\PayrollRunEmployee;
use App\Models\PayrollRunEmployeeLine;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class PayslipService
{
    public function generate(PayrollRunEmployee $runEmployee): Payslip
    {
        $employee = $runEmployee->employee;
        $payrollMonth = $runEmployee->payrollRun->payroll_month;

        $lines = $runEmployee->lines()->with('component')->get();
        $earningLines = $lines->where('component_type', SalaryComponent::TYPE_EARNING);
        $deductionLines = $lines->where('component_type', SalaryComponent::TYPE_DEDUCTION);

        $bank = $employee->bankDetails()->where('is_primary', true)->first();
        $statutory = $employee->statutoryDetail;

        [$ytdEarnings, $ytdTds] = $this->yearToDateTotals($employee->id, $payrollMonth);

        $pdf = Pdf::loadView('payroll.payslip', [
            'employee' => $employee,
            'company' => $employee->company,
            'monthLabel' => Carbon::createFromFormat('Y-m', $payrollMonth)->format('F Y'),
            'runEmployee' => $runEmployee,
            'earningLines' => $earningLines,
            'deductionLines' => $deductionLines,
            'pan' => $statutory?->pan,
            'uan' => $statutory?->uan,
            'maskedAccount' => $bank?->masked_account_number ?? 'N/A',
            'netPayInWords' => $this->amountInWords((float) $runEmployee->net_pay),
            'ytdEarnings' => $ytdEarnings,
            'ytdTds' => $ytdTds,
        ]);

        $path = "payslips/{$employee->id}/{$payrollMonth}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return Payslip::updateOrCreate(
            ['payroll_run_employee_id' => $runEmployee->id],
            [
                'employee_id' => $employee->id,
                'payroll_month' => $payrollMonth,
                'pdf_path' => $path,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * @return array{0: float, 1: float} [ytdEarnings, ytdTds]
     */
    private function yearToDateTotals(int $employeeId, string $payrollMonth): array
    {
        $year = (int) substr($payrollMonth, 0, 4);
        $month = (int) substr($payrollMonth, 5, 2);

        $runEmployeeIds = PayrollRunEmployee::query()
            ->where('employee_id', $employeeId)
            ->whereHas('payrollRun', function ($q) use ($year, $month) {
                $q->where('payroll_month', '>=', sprintf('%04d-%02d', $year, 1))
                    ->where('payroll_month', '<=', sprintf('%04d-%02d', $year, $month));
            })
            ->pluck('id');

        $earnings = PayrollRunEmployeeLine::query()
            ->whereIn('payroll_run_employee_id', $runEmployeeIds)
            ->where('component_type', SalaryComponent::TYPE_EARNING)
            ->sum('amount');

        $tds = PayrollRunEmployeeLine::query()
            ->whereIn('payroll_run_employee_id', $runEmployeeIds)
            ->where('label', 'like', '%TDS%')
            ->sum('amount');

        return [(float) $earnings, (float) $tds];
    }

    public function amountInWords(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = 'Rupees '.$this->numberToWords($rupees);

        if ($paise > 0) {
            $words .= ' and '.$this->numberToWords($paise).' Paise';
        }

        return $words.' Only';
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $convert = function (int $n) use (&$convert, $ones, $tens): string {
            if ($n < 20) {
                return $ones[$n];
            }
            if ($n < 100) {
                return trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
            }

            return trim($ones[intdiv($n, 100)].' Hundred '.$convert($n % 100));
        };

        $parts = [];
        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        $lakh = intdiv($number, 100000);
        $number %= 100000;
        $thousand = intdiv($number, 1000);
        $number %= 1000;
        $hundred = $number;

        if ($crore > 0) {
            $parts[] = $convert($crore).' Crore';
        }
        if ($lakh > 0) {
            $parts[] = $convert($lakh).' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = $convert($thousand).' Thousand';
        }
        if ($hundred > 0) {
            $parts[] = $convert($hundred);
        }

        return implode(' ', $parts);
    }
}
