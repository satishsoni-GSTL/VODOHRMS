<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\PrejoiningInvite;
use App\Services\EmployeeProfileService;
use App\Services\PrejoiningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The new hire's side of the pre-joining portal. Every route is keyed by the invite token;
 * nothing beyond "verify your email" is shown until the emailed code is confirmed in this
 * browser session.
 */
class PrejoiningController extends Controller
{
    public function __construct(
        private readonly PrejoiningService $service,
        private readonly EmployeeProfileService $profile,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invite = $this->resolve($token);

        if (! $invite) {
            return response()->view('prejoining.unavailable', [], 404);
        }

        if (! $this->isVerified($request, $invite)) {
            return response()->view('prejoining.verify', [
                'invite' => $invite,
                'token' => $token,
                'codeSent' => $request->session()->get('prejoining.code_sent') === $invite->id,
            ]);
        }

        $employee = $invite->employee->fresh(['statutoryDetail', 'bankDetails', 'documents', 'company']);

        return response()->view('prejoining.form', [
            'invite' => $invite,
            'token' => $token,
            'employee' => $employee,
            'data' => $this->profile->formData($employee),
            'completion' => $this->profile->completion($employee),
            'editable' => $invite->isEditable(),
        ]);
    }

    public function sendCode(Request $request, string $token): RedirectResponse
    {
        $invite = $this->resolve($token) ?? abort(404);

        if (($wait = $this->service->sendOtp($invite)) !== null) {
            return back()->withErrors(['code' => 'Too many codes requested. Try again in '.ceil($wait / 60).' minute(s).']);
        }

        $request->session()->flash('prejoining.code_sent', $invite->id);

        return redirect()->route('prejoining.show', $token)->with('status', 'We emailed a 6-digit code to '.$invite->maskedEmail().'.');
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $invite = $this->resolve($token) ?? abort(404);
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $this->service->verifyOtp($invite, $data['code'])) {
            $request->session()->flash('prejoining.code_sent', $invite->id);

            return back()->withErrors(['code' => 'That code is invalid or has expired. Check the latest email or request a new code.']);
        }

        $request->session()->regenerate();
        $request->session()->put("prejoining.verified.{$invite->id}", now()->addHours(PrejoiningService::SESSION_HOURS)->timestamp);

        return redirect()->route('prejoining.show', $token);
    }

    /** Save the form (and any uploaded documents); with action=submit, also submit to HR. */
    public function save(Request $request, string $token): RedirectResponse
    {
        $invite = $this->authorized($request, $token);

        $request->validate([
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'action' => ['nullable', Rule::in(['save', 'submit'])],
        ], [
            'documents.*.mimes' => 'Documents must be a photo (JPG, PNG, WEBP) or a PDF.',
            'documents.*.max' => 'Each document can be up to 10 MB.',
        ]);

        try {
            $this->service->save($invite, $request->except(['_token', 'documents', 'action']));

            foreach ((array) $request->file('documents', []) as $type => $file) {
                if ($file && array_key_exists($type, EmployeeDocument::types())) {
                    $this->profile->addDocument($invite->employee, $type, $file, 'prejoining');
                }
            }

            if ($request->input('action') === 'submit') {
                $this->service->submit($invite->fresh('employee'));

                return redirect()->route('prejoining.show', $token)->with('status', 'Thank you! Your details have been sent to HR.');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput($request->except('documents'));
        }

        return redirect()->route('prejoining.show', $token)->with('status', 'Saved. You can come back to this link to finish later.');
    }

    public function deleteDocument(Request $request, string $token, EmployeeDocument $document): RedirectResponse
    {
        $invite = $this->authorized($request, $token);

        abort_unless($document->employee_id === $invite->employee_id && $invite->isEditable(), 404);

        try {
            $this->profile->deleteDocument($document);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('prejoining.show', $token)->with('status', 'Document removed.');
    }

    public function signOut(Request $request, string $token): RedirectResponse
    {
        if ($invite = $this->resolve($token)) {
            $request->session()->forget("prejoining.verified.{$invite->id}");
        }

        return redirect()->route('prejoining.show', $token);
    }

    private function resolve(string $token): ?PrejoiningInvite
    {
        $invite = PrejoiningInvite::findByToken($token);

        return $invite && $invite->isUsable() ? $invite : null;
    }

    private function authorized(Request $request, string $token): PrejoiningInvite
    {
        $invite = $this->resolve($token) ?? abort(404);

        abort_unless($this->isVerified($request, $invite), 403, 'Please verify your email first.');

        return $invite;
    }

    private function isVerified(Request $request, PrejoiningInvite $invite): bool
    {
        return (int) $request->session()->get("prejoining.verified.{$invite->id}", 0) > now()->timestamp;
    }
}
