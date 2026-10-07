<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Base for the employee mobile-app API (routes/api.php, prefix /api/mobile, middleware
 * auth.mobile). Every endpoint works on the signed-in employee's own data and reuses the
 * same services as the web panel, so business rules (balances, workflow, LOP…) match.
 */
abstract class MobileController extends Controller
{
    /** Attachments use the same disk and folders as the web panel's upload fields. */
    protected const DISK = 'local';

    protected function user(Request $request): User
    {
        return $request->user();
    }

    protected function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }

    protected function storeUpload(?UploadedFile $file, string $directory): ?string
    {
        return $file?->store($directory, self::DISK);
    }

    protected static function statusLabel(?string $status): string
    {
        return $status ? ucwords(str_replace('_', ' ', $status)) : '';
    }

    /**
     * The approval trail for a request: who acted, what they did and their remarks — so the
     * employee can see why something was rejected or sent back.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function approvalTrail(?ApprovalInstance $instance): array
    {
        if (! $instance) {
            return [];
        }

        return $instance->actions()->with('approver')->get()
            ->map(fn (ApprovalAction $a) => [
                'level' => $a->level,
                'action' => $a->action,
                'action_label' => self::statusLabel($a->action),
                'by' => $a->approver?->name,
                'remarks' => $a->remarks,
                'at' => $a->acted_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** Latest remarks from an approver (e.g. the rejection reason), if any. */
    protected static function latestRemarks(?ApprovalInstance $instance): ?string
    {
        return $instance?->actions()->whereNotNull('remarks')->latest('acted_at')->value('remarks');
    }
}
