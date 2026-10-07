<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Holiday;
use App\Models\OptionalHolidayClaim;
use App\Services\OptionalHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends MobileController
{
    /** The year's holiday calendar, with optional holidays marked claimable / claimed. */
    public function index(Request $request, OptionalHolidayService $optional): JsonResponse
    {
        $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $employee = $this->employee($request);
        $year = (int) $request->query('year', now()->year);

        $claims = OptionalHolidayClaim::query()
            ->where('employee_id', $employee->id)
            ->where('status', OptionalHolidayClaim::STATUS_CLAIMED)
            ->get()
            ->keyBy('holiday_id');

        $claimable = $optional->claimableHolidays($employee)->pluck('id');

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $h) => self::present($h) + [
                'claim_id' => $claims->get($h->id)?->id,
                'claimed' => $claims->has($h->id),
                'can_claim' => $claimable->contains($h->id),
                'can_cancel_claim' => $claims->has($h->id) && ! $h->date->isPast(),
            ]);

        return response()->json([
            'year' => $year,
            'optional_limit' => $optional->limitFor($employee, $year),
            'optional_used' => $optional->usedFor($employee, $year),
            'holidays' => $holidays,
        ]);
    }

    public function claim(Request $request, Holiday $holiday, OptionalHolidayService $optional): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $optional->claim($this->employee($request), $holiday, $data['reason'] ?? null, $this->user($request));

        return response()->json(['ok' => true, 'message' => 'Optional holiday claimed.']);
    }

    public function cancelClaim(Request $request, OptionalHolidayClaim $claim, OptionalHolidayService $optional): JsonResponse
    {
        abort_unless($claim->employee_id === $this->employee($request)->id, 404);

        $optional->cancel($claim);

        return response()->json(['ok' => true, 'message' => 'Optional holiday claim cancelled.']);
    }

    /** @return array<string, mixed> */
    public static function present(Holiday $h): array
    {
        return [
            'id' => $h->id,
            'name' => trim($h->name, " \t\n\r\0\x0B\u{00A0}"),
            'date' => $h->date->toDateString(),
            'type' => $h->type,
            'type_label' => Holiday::TYPES[$h->type] ?? self::statusLabel($h->type),
            'is_optional' => $h->isOptional(),
        ];
    }
}
