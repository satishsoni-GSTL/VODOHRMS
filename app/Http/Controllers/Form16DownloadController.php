<?php

namespace App\Http\Controllers;

use App\Models\Form16;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Form16DownloadController extends Controller
{
    public function __invoke(Request $request, Form16 $form16): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user->can('tax.view') || $user->employee_id === $form16->employee_id,
            403
        );

        abort_unless(Storage::disk('local')->exists($form16->pdf_path), 404);

        return Storage::disk('local')->download(
            $form16->pdf_path,
            "form16-{$form16->financialYear->name}.pdf"
        );
    }
}
