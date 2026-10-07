<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;
use ZipArchive;

/**
 * Employee-wise monthly expense statement as ONE shareable PDF: a summary of every expense
 * line in the month (by expense date), followed by every attached bill — photos placed on
 * their own page, PDF bills merged page by page — each stamped with its reference (A1, A2…)
 * so it can be matched to the summary.
 *
 * Visibility follows ExpenseMonthlySummaryService: HR-tier sees everyone, managers their
 * reporting line, employees themselves.
 */
class ExpenseStatementService
{
    private const DISK = 'local';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    // A4 in mm (FPDF units).
    private const A4_W = 210;

    private const A4_H = 297;

    public function __construct(private readonly ExpenseMonthlySummaryService $summary) {}

    public function canDownload(User $user, Employee $employee): bool
    {
        return $this->summary->canViewEmployee($user, $employee->id);
    }

    public function fileName(Employee $employee, string $month): string
    {
        return "expense-statement-{$employee->employee_code}-{$month}.pdf";
    }

    /** The complete statement PDF (summary + all bills) as a binary string. */
    public function build(Employee $employee, string $month): string
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $lines = ExpenseClaimLine::query()
            ->with(['claim', 'category'])
            ->whereHas('claim', fn ($q) => $q->where('employee_id', $employee->id))
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        // Attachment references in summary order: A1, A2…
        $n = 0;
        $rows = $lines->map(function (ExpenseClaimLine $l) use (&$n) {
            $status = $l->claim?->status;

            return [
                'line' => $l,
                'ref' => $l->receipt_path ? 'A'.(++$n) : null,
                'date' => $l->expense_date->toDateString(),
                'category' => $l->category?->name ?? '—',
                'description' => $l->description,
                'vendor' => $l->vendor,
                'bill_number' => $l->bill_number,
                'payment_mode' => $l->payment_mode,
                'requested_amount' => (float) $l->requested_amount,
                'approved_amount' => $l->approved_amount === null ? null : (float) $l->approved_amount,
                'claim_number' => (string) $l->claim?->claim_number,
                'status_label' => ExpenseClaim::STATUSES[$status] ?? (string) $status,
                'counts' => ! in_array($status, ExpenseClaim::NON_COUNTING_STATUSES, true),
            ];
        });

        $counting = $rows->where('counts', true);

        $summaryPdf = Pdf::loadView('reports.expense-consolidated', [
            'employee' => $employee->loadMissing(['company', 'department', 'designation', 'reportingManager']),
            'monthLabel' => $start->format('F Y'),
            'generatedAt' => now()->format('d M Y, h:i A'),
            'logo' => $this->logoDataUri(),
            'lines' => $rows,
            'attachmentCount' => $n,
            'byCategory' => $counting->groupBy('category')->map(fn (Collection $g) => $g->sum('requested_amount'))->sortKeys()->all(),
            'totalRequested' => $counting->sum('requested_amount'),
            'totalApproved' => $counting->sum(fn ($r) => $r['approved_amount'] ?? 0),
        ])->setPaper('a4')->output();

        return $this->assemble($summaryPdf, $rows->whereNotNull('ref')->values());
    }

    /**
     * One statement PDF per employee who has expenses in the month (within the user's scope),
     * zipped. Returns the temp zip path, or null when there is nothing to export.
     */
    public function zipForMonth(string $month, User $user): ?string
    {
        $summary = $this->summary->summary($month, $user);
        $employees = Employee::query()->whereIn('id', $summary['employee_ids'] ?: [0])->orderBy('employee_code')->get();

        if ($employees->isEmpty()) {
            return null;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'expst').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($employees as $employee) {
            $zip->addFromString($this->fileName($employee, $month), $this->build($employee, $month));
        }

        $zip->close();

        return $zipPath;
    }

    // ---- assembly -------------------------------------------------------------------------

    /** @param  Collection<int, array<string, mixed>>  $attachments */
    private function assemble(string $summaryPdf, Collection $attachments): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle('Monthly Expense Statement', true);
        $pdf->SetCreator('VODO HRMS', true);

        $this->importPdf($pdf, $this->tempFile($summaryPdf, 'pdf'));

        foreach ($attachments as $row) {
            $path = $row['line']->receipt_path;
            $absolute = Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->path($path) : null;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! $absolute) {
                $this->placeholderPage($pdf, $row, 'The bill file is missing from the server.');

                continue;
            }

            try {
                if ($ext === 'pdf') {
                    $this->importPdf($pdf, $absolute, $row);
                } elseif (in_array($ext, self::IMAGE_EXTENSIONS, true) || str_starts_with((string) @mime_content_type($absolute), 'image/')) {
                    $this->imagePage($pdf, $absolute, $row);
                } else {
                    $this->placeholderPage($pdf, $row, "This bill is a .{$ext} file, which can't be shown inside a PDF. Open it from the expense claim in HRMS.");
                }
            } catch (Throwable $e) {
                Log::warning('Expense statement: could not embed bill', ['line' => $row['line']->id, 'error' => $e->getMessage()]);
                $this->placeholderPage($pdf, $row, 'This bill could not be embedded (unsupported or damaged file). Open it from the expense claim in HRMS.');
            }
        }

        return $pdf->Output('S');
    }

    /** @param  array<string, mixed>|null  $row  stamp each page with the bill reference when set */
    private function importPdf(Fpdi $pdf, string $file, ?array $row = null): void
    {
        $pages = $pdf->setSourceFile($file);

        for ($i = 1; $i <= $pages; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            if ($row) {
                $this->stamp($pdf, $row, $pages > 1 ? " (page {$i}/{$pages})" : '', $size['width']);
            }
        }
    }

    /** @param  array<string, mixed>  $row */
    private function imagePage(Fpdi $pdf, string $file, array $row): void
    {
        [$jpeg, $w, $h] = $this->normalisedJpeg($file);

        $landscape = $w > $h;
        $pageW = $landscape ? self::A4_H : self::A4_W;
        $pageH = $landscape ? self::A4_W : self::A4_H;
        $pdf->AddPage($landscape ? 'L' : 'P', 'A4');

        $this->header($pdf, $row, '', $pageW);

        // Fit inside the page below the header, keeping the aspect ratio.
        $boxX = 10;
        $boxY = 24;
        $boxW = $pageW - 20;
        $boxH = $pageH - 34;
        $scale = min($boxW / $w, $boxH / $h);
        $drawW = $w * $scale;
        $drawH = $h * $scale;

        $pdf->Image($jpeg, $boxX + ($boxW - $drawW) / 2, $boxY + ($boxH - $drawH) / 2, $drawW, $drawH, 'JPG');
        @unlink($jpeg);
    }

    /** @param  array<string, mixed>  $row */
    private function placeholderPage(Fpdi $pdf, array $row, string $message): void
    {
        $pdf->AddPage('P', 'A4');
        $this->header($pdf, $row, '', self::A4_W);

        $pdf->SetXY(20, 60);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetTextColor(180, 83, 9);
        $pdf->Cell(0, 8, $this->latin1('Bill not shown'), 0, 1);
        $pdf->SetX(20);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->SetTextColor(55, 65, 81);
        $pdf->MultiCell(170, 6, $this->latin1($message."\n\nFile: ".basename($row['line']->receipt_path)));
    }

    /** Header band on our own pages. */
    private function header(Fpdi $pdf, array $row, string $suffix, float $pageW): void
    {
        $pdf->SetFillColor(12, 132, 129);
        $pdf->Rect(0, 0, $pageW, 16, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetXY(10, 4);
        $pdf->Cell(16, 8, $row['ref'], 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell($pageW - 36, 8, $this->latin1($this->caption($row).$suffix), 0, 0);
    }

    /** Small label over the top of an imported PDF bill page. */
    private function stamp(Fpdi $pdf, array $row, string $suffix, float $pageW): void
    {
        $pdf->SetFillColor(12, 132, 129);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 8);
        $text = $this->latin1($row['ref'].'  '.$this->caption($row).$suffix);
        $w = min($pdf->GetStringWidth($text) + 6, $pageW);
        $pdf->SetXY(0, 0);
        $pdf->Cell($w, 6, $text, 0, 0, 'L', true);
    }

    private function caption(array $row): string
    {
        return implode('  ·  ', array_filter([
            Carbon::parse($row['date'])->format('d M Y'),
            $row['category'],
            'Rs. '.number_format($row['requested_amount'], 2),
            $row['claim_number'],
            $row['vendor'],
        ]));
    }

    /**
     * Re-encode any supported image as an upright, size-capped JPEG (FPDF handles JPEG
     * reliably; this also covers WEBP/BMP/interlaced PNG and phone-camera EXIF rotation).
     *
     * @return array{0: string, 1: int, 2: int} [temp jpeg path, width, height]
     */
    private function normalisedJpeg(string $file): array
    {
        $img = @imagecreatefromstring((string) file_get_contents($file));

        if (! $img) {
            throw new \RuntimeException('Unsupported image format');
        }

        if (function_exists('exif_read_data') && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
            $orientation = (int) (@exif_read_data($file)['Orientation'] ?? 1);
            $img = match ($orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        // Flatten transparency onto white and cap the size (keeps the PDF small).
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, 2000 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $path = tempnam(sys_get_temp_dir(), 'bill').'.jpg';
        imagejpeg($out, $path, 82);

        return [$path, $nw, $nh];
    }

    private function tempFile(string $contents, string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exs').'.'.$ext;
        file_put_contents($path, $contents);
        register_shutdown_function(fn () => @unlink($path));

        return $path;
    }

    /** FPDF core fonts are Latin-1; transliterate anything else. */
    private function latin1(string $text): string
    {
        return (string) @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/globalspace-logo.png');

        return is_readable($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
