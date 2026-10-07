<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\PolicyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PolicyController extends MobileController
{
    public function index(): JsonResponse
    {
        $items = PolicyDocument::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (PolicyDocument $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'description' => $d->description,
                'file_name' => $d->file_name,
                'updated_at' => $d->updated_at?->toIso8601String(),
            ]);

        return response()->json(['items' => $items]);
    }

    public function download(Request $request, PolicyDocument $policy): StreamedResponse
    {
        abort_unless($policy->is_active, 404);
        abort_unless($policy->file_path && Storage::disk(self::DISK)->exists($policy->file_path), 404);

        return Storage::disk(self::DISK)->download($policy->file_path, $policy->file_name);
    }
}
