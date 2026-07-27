<?php

namespace App\Http\Controllers;

use App\Models\PolicyDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PolicyDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, PolicyDocument $policyDocument): StreamedResponse
    {
        $user = $request->user();

        abort_unless($policyDocument->is_active || $user->can('policy.manage'), 404);
        abort_unless(Storage::disk('local')->exists($policyDocument->file_path), 404);

        return Storage::disk('local')->download($policyDocument->file_path, $policyDocument->file_name);
    }
}
