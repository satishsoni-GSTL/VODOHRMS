<?php

namespace App\Http\Controllers;

use App\Models\ProbationReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProbationLetterDownloadController extends Controller
{
    public function __invoke(Request $request, ProbationReview $review): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user->can('employee.view')
                || $user->employee_id === $review->employee_id
                || in_array($review->employee_id, $user->employee?->allSubordinateIds() ?? [], true),
            403
        );

        abort_unless($review->letter_path && Storage::disk('local')->exists($review->letter_path), 404);

        $name = $review->recommendation === ProbationReview::RECOMMEND_CONFIRM ? 'confirmation-letter' : 'probation-extension-letter';

        return Storage::disk('local')->download($review->letter_path, "{$name}-{$review->employee->employee_code}.pdf");
    }
}
