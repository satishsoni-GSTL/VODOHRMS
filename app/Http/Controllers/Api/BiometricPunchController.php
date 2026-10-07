<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\BiometricPunchService;
use Illuminate\Http\Request;
use Throwable;

class BiometricPunchController extends Controller
{
    public function store(Request $request, BiometricPunchService $punchService)
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('biometric_device');

        $data = $request->validate([
            'punches' => ['required', 'array', 'min:1'],
            'punches.*.device_user_id' => ['required', 'string'],
            // A real device never sends future punches, and the agent only back-fills recent
            // history — anything else is a forged / bad request.
            'punches.*.punch_time' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(10)->toDateTimeString(), 'after:'.now()->subDays(45)->toDateString()],
            'punches.*.punch_type' => ['nullable', 'string', 'in:in,out'],
        ]);

        $results = [
            'matched' => 0,
            'unmatched' => 0,
            'duplicate' => 0,
            'failed' => 0,
        ];

        foreach ($data['punches'] as $punch) {
            try {
                $results[$punchService->ingest($device, $punch, $request->ip())]++;
            } catch (Throwable $e) {
                $results['failed']++;
                report($e);
            }
        }

        return response()->json(['summary' => $results]);
    }
}
