<?php

namespace App\Http\Controllers;

use App\Models\SharedFileRecipient;
use App\Services\FileShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recipient side of file sharing, served on the file-share domain (config('fileshare.url')).
 * Every route is keyed by the recipient's link token; nothing beyond the "verify your
 * email" screen is shown until the emailed code has been confirmed in this browser session.
 */
class FileShareController extends Controller
{
    public function __construct(private readonly FileShareService $service) {}

    public function show(Request $request, string $token): Response
    {
        $recipient = $this->resolve($token);

        if ($recipient === null) {
            return $this->unavailable();
        }

        if (! $this->isVerified($request, $recipient)) {
            return response()->view('fileshare.verify', [
                'recipient' => $recipient,
                'file' => $recipient->sharedFile,
                'token' => $token,
                'codeSent' => $request->session()->get('fileshare.code_sent') === $recipient->id,
            ]);
        }

        $this->service->recordView($recipient);

        $file = $recipient->sharedFile;
        $file->load(['rootComments' => fn ($q) => $q->oldest()->with('replies')]);

        return response()->view('fileshare.show', [
            'recipient' => $recipient,
            'file' => $file,
            'token' => $token,
        ]);
    }

    public function sendCode(Request $request, string $token): RedirectResponse
    {
        $recipient = $this->resolve($token) ?? abort(404);

        $wait = $this->service->sendOtp($recipient);

        if ($wait !== null) {
            return back()->withErrors(['code' => 'Too many codes requested. Try again in '.ceil($wait / 60).' minute(s).']);
        }

        $request->session()->flash('fileshare.code_sent', $recipient->id);

        return redirect()->route('fileshare.show', $token)->with('status', 'We emailed a 6-digit code to '.$recipient->maskedEmail().'.');
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $recipient = $this->resolve($token) ?? abort(404);

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $this->service->verifyOtp($recipient, $data['code'])) {
            $request->session()->flash('fileshare.code_sent', $recipient->id);

            return back()->withErrors(['code' => 'That code is invalid or has expired. Check the latest email or request a new code.']);
        }

        $request->session()->regenerate();
        $request->session()->put(
            "fileshare.verified.{$recipient->id}",
            now()->addHours(config('fileshare.session_hours'))->timestamp,
        );

        return redirect()->route('fileshare.show', $token);
    }

    public function preview(Request $request, string $token): Response
    {
        $recipient = $this->authorizedRecipient($request, $token);
        $file = $recipient->sharedFile;

        abort_if($file->previewKind() === null, 404);

        return Storage::disk(config('fileshare.disk'))->response($file->file_path, $file->file_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $request, string $token): Response
    {
        $recipient = $this->authorizedRecipient($request, $token);
        $file = $recipient->sharedFile;

        abort_unless($file->allow_download, 403, 'The owner has disabled downloads for this file.');

        $this->service->log($file, 'downloaded', $recipient);

        return Storage::disk(config('fileshare.disk'))->download($file->file_path, $file->file_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function comment(Request $request, string $token): RedirectResponse
    {
        $recipient = $this->authorizedRecipient($request, $token);
        $file = $recipient->sharedFile;

        abort_unless($recipient->can_comment, 403, 'Commenting is not enabled for you on this file.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('shared_file_comments', 'id')->where('shared_file_id', $file->id)],
        ]);

        $parent = isset($data['parent_id']) ? $file->comments()->find($data['parent_id']) : null;

        $comment = $this->service->addComment($file, $data['body'], $parent, recipient: $recipient);

        return redirect()->to(route('fileshare.show', $token).'#comment-'.$comment->id)
            ->with('status', $parent ? 'Reply posted.' : 'Comment posted.');
    }

    public function signOut(Request $request, string $token): RedirectResponse
    {
        $recipient = $this->resolve($token);

        if ($recipient) {
            $request->session()->forget("fileshare.verified.{$recipient->id}");
        }

        return redirect()->route('fileshare.show', $token);
    }

    /** Recipient behind a live link, or null when the link/file is revoked, expired or gone. */
    private function resolve(string $token): ?SharedFileRecipient
    {
        $recipient = SharedFileRecipient::findByToken($token);

        if (! $recipient || $recipient->isRevoked() || ! $recipient->sharedFile?->isAvailable()) {
            return null;
        }

        return $recipient;
    }

    private function authorizedRecipient(Request $request, string $token): SharedFileRecipient
    {
        $recipient = $this->resolve($token) ?? abort(404);

        abort_unless($this->isVerified($request, $recipient), 403, 'Please verify your email first.');

        return $recipient;
    }

    private function isVerified(Request $request, SharedFileRecipient $recipient): bool
    {
        return (int) $request->session()->get("fileshare.verified.{$recipient->id}", 0) > now()->timestamp;
    }

    private function unavailable(): Response
    {
        return response()->view('fileshare.unavailable', [], 404);
    }
}
