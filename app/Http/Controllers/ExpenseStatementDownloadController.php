<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\ExpenseStatementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Monthly expense statement downloads: one employee's single PDF (summary + every bill),
 * or a ZIP of every visible employee's statement for the month.
 */
class ExpenseStatementDownloadController extends Controller
{
    public function __construct(private readonly ExpenseStatementService $statements) {}

    public function employee(Request $request, Employee $employee): Response
    {
        $month = $this->month($request);
        abort_unless($this->statements->canDownload($request->user(), $employee), 403);

        return response($this->statements->build($employee, $month), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->statements->fileName($employee, $month).'"',
        ]);
    }

    public function all(Request $request): BinaryFileResponse
    {
        $month = $this->month($request);
        $user = $request->user();

        abort_unless($user->can('expense.view') || ($user->employee?->directReports()->exists() ?? false), 403);

        @set_time_limit(300);
        $zip = $this->statements->zipForMonth($month, $user);
        abort_if($zip === null, 404, 'No expenses in this month.');

        return response()->download($zip, "expense-statements-{$month}.zip")->deleteFileAfterSend();
    }

    private function month(Request $request): string
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        return $request->query('month');
    }
}
