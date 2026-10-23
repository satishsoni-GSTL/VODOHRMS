<?php

namespace App\Http\Controllers;

use App\Models\SharedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedFileDownloadController extends Controller
{
    public function __invoke(Request $request, SharedFile $sharedFile): StreamedResponse
    {
        abort_unless($request->user()->can('view', $sharedFile), 403);
        abort_unless(Storage::disk(config('fileshare.disk'))->exists($sharedFile->file_path), 404);

        return Storage::disk(config('fileshare.disk'))->download($sharedFile->file_path, $sharedFile->file_name);
    }
}
