<?php

namespace App\Http\Controllers;

use App\Models\ExpenseClaimLine;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReceiptDownloadController extends Controller
{
    public function __invoke(Request $request, ExpenseClaimLine $expenseClaimLine): StreamedResponse
    {
        $user = $request->user();
        $claim = $expenseClaimLine->claim;

        $visibleIds = $user->employee ? [$user->employee->id, ...$user->employee->allSubordinateIds()] : [];

        $canView = $user->can('expense.view')
            || in_array($claim->employee_id, $visibleIds, true)
            || ($claim->approval_instance_id && app(ApprovalWorkflowService::class)->canUserActOnInstance($claim->approvalInstance, $user));

        abort_unless($canView, 403);
        abort_unless($expenseClaimLine->receipt_path && Storage::disk('local')->exists($expenseClaimLine->receipt_path), 404);

        return Storage::disk('local')->download($expenseClaimLine->receipt_path);
    }
}
