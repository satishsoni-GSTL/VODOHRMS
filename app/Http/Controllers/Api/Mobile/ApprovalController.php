<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Models\AttendanceRegularization;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\LeaveApplication;
use App\Models\Resignation;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Manager / HR approvals — the mobile equivalent of the web "Pending Approvals" page. Only
 * requests the user can act on right now are listed (pending, at a level they approve,
 * not already actioned by them), and acting goes through the same workflow service.
 */
class ApprovalController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $items = self::actionable($this->user($request))
            ->map(fn (ApprovalInstance $i) => self::present($i))
            ->values();

        return response()->json(['items' => $items]);
    }

    public function act(Request $request, ApprovalInstance $instance, ApprovalWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([ApprovalAction::ACTION_APPROVE, ApprovalAction::ACTION_REJECT, ApprovalAction::ACTION_SEND_BACK])],
            'remarks' => [
                Rule::requiredIf(fn () => in_array($request->input('action'), [ApprovalAction::ACTION_REJECT, ApprovalAction::ACTION_SEND_BACK], true)),
                'nullable', 'string', 'max:2000',
            ],
        ]);

        // act() re-checks that this user may act on the request at its current level.
        $workflow->act($instance, $this->user($request), $data['action'], $data['remarks'] ?? null);

        return response()->json([
            'ok' => true,
            'message' => match ($data['action']) {
                ApprovalAction::ACTION_APPROVE => 'Approved.',
                ApprovalAction::ACTION_REJECT => 'Rejected.',
                default => 'Sent back to the employee.',
            },
        ]);
    }

    /** @return Collection<int, ApprovalInstance> */
    public static function actionable(User $user): Collection
    {
        $workflow = app(ApprovalWorkflowService::class);

        return ApprovalInstance::query()
            ->where('status', ApprovalInstance::STATUS_PENDING)
            ->with(['requestable.employee', 'workflowDefinition'])
            ->latest()
            ->get()
            ->filter(fn (ApprovalInstance $i) => $i->requestable && $workflow->canUserActOnInstance($i, $user))
            ->values();
    }

    /**
     * Anyone with direct reports, a workflow-level role, or a module-manage permission may
     * see the Approvals tab (it may simply be empty).
     */
    public static function isApprover(User $user): bool
    {
        return ($user->employee?->directReports()->exists() ?? false)
            || $user->hasAnyRole(['Super Admin', 'HR Admin', 'Finance Admin', 'HR Executive'])
            || $user->can('leave.manage') || $user->can('attendance.manage');
    }

    /** @return array<string, mixed> */
    private static function present(ApprovalInstance $instance): array
    {
        $r = $instance->requestable;

        [$type, $title, $details] = match (true) {
            $r instanceof LeaveApplication => ['leave', $r->leaveType?->name ?? 'Leave', [
                'From' => $r->from_date?->format('d M Y'),
                'To' => $r->to_date?->format('d M Y'),
                'Days' => rtrim(rtrim((string) $r->days, '0'), '.').($r->is_half_day ? ' (half day)' : ''),
                'Reason' => $r->reason,
            ]],
            $r instanceof AttendanceRegularization => ['regularization', 'Attendance Regularization', [
                'Date' => $r->attendance_date?->format('d M Y'),
                'Type' => AttendanceRegularization::TYPES[$r->request_type] ?? $r->request_type,
                'Check-in' => $r->requested_values['first_in'] ?? null,
                'Check-out' => $r->requested_values['last_out'] ?? null,
                'Reason' => $r->reason,
            ]],
            $r instanceof WorkFromHomeRequest => ['wfh', 'Work From Home', [
                'From' => $r->from_date?->format('d M Y'),
                'To' => $r->to_date?->format('d M Y'),
                'Working days' => (string) $r->total_days,
                'Reason' => $r->reason,
            ]],
            $r instanceof ExpenseClaim => ['expense', "Expense {$r->claim_number}", [
                'Claim date' => $r->claim_date?->format('d M Y'),
                'Amount' => '₹'.number_format((float) $r->total_requested_amount, 2),
                'Items' => (string) $r->lines()->count(),
                'Project / client' => $r->project_client,
            ]],
            $r instanceof EmployeeLoan => ['loan', $r->type === EmployeeLoan::TYPE_SALARY_ADVANCE ? 'Salary Advance' : 'Loan', [
                'Amount' => '₹'.number_format((float) $r->requested_amount, 2),
                'Requested on' => $r->request_date?->format('d M Y'),
                'Reason' => $r->reason,
            ]],
            $r instanceof Resignation => ['resignation', 'Resignation', [
                'Resigned on' => $r->resignation_date?->format('d M Y'),
                'Requested last day' => $r->requested_last_working_date?->format('d M Y'),
                'Reason' => $r->reason,
            ]],
            default => ['other', class_basename($r), []],
        };

        return [
            'id' => $instance->id,
            'type' => $type,
            'title' => $title,
            'workflow' => $instance->workflowDefinition?->name,
            'level' => $instance->current_level,
            'employee' => [
                'name' => ProfileController::cleanName($r->employee?->full_name),
                'employee_code' => $r->employee?->employee_code,
            ],
            'details' => collect($details)->filter(fn ($v) => filled($v))->map(fn ($v, $k) => ['label' => $k, 'value' => (string) $v])->values(),
            'submitted_at' => $instance->created_at?->toIso8601String(),
            'trail' => self::approvalTrail($instance),
        ];
    }
}
