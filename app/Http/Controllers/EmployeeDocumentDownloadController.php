<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, EmployeeDocument $document): StreamedResponse
    {
        $user = $request->user();

        // Identity documents: HR (sensitive-data permission) or the employee themself.
        abort_unless(
            $user->can('employee.view-sensitive') || $user->can('onboarding.manage') || $user->employee_id === $document->employee_id,
            403
        );

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name ?: basename($document->file_path));
    }
}
