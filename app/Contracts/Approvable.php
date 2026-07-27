<?php

namespace App\Contracts;

use App\Models\WorkflowLevel;

/**
 * Implemented by Eloquent models that flow through the ApprovalWorkflowService
 * (LeaveApplication, AttendanceRegularization, and future Expense/Loan/Resignation).
 * Consumers should type-hint `Approvable&\Illuminate\Database\Eloquent\Model`.
 */
interface Approvable
{
    /**
     * The workflow_definitions.module this request routes through.
     */
    public function getApprovalModule(): string;

    /**
     * Values evaluated against WorkflowLevel::condition_rules (e.g. ['amount' => 1200, 'grade_level' => 3]).
     *
     * @return array<string, mixed>
     */
    public function getApprovalConditionContext(): array;

    public function getRequestingEmployeeId(): int;

    /**
     * Called by ApprovalWorkflowService whenever the request's approval state changes.
     *
     * @param  'approved_level'|'approved'|'rejected'|'sent_back'  $outcome
     */
    public function applyApprovalOutcome(string $outcome, ?WorkflowLevel $level = null): void;
}
