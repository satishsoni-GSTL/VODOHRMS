<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MicrosoftGraphMailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('mail.mailers.graph', [
            'transport' => 'graph',
            'tenant_id' => 'tenant-123',
            'client_id' => 'client-abc',
            'client_secret' => 'super-secret',
            'sender' => 'itsecurity@globalspace.in',
        ]);
        Config::set('mail.default', 'graph');
        Config::set('mail.from', ['address' => 'itsecurity@globalspace.in', 'name' => 'VODOHRMS']);

        Cache::forget('ms_graph_mail_access_token');
    }

    public function test_sends_via_graph_sendmail_with_correct_recipient_subject_and_body(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/*' => Http::response('', 202),
        ]);

        Mail::raw('Hello from the test suite.', function ($message) {
            $message->to('someone@example.com')->subject('Test Subject');
        });

        Http::assertSent(fn ($request) => str_contains($request->url(), 'login.microsoftonline.com')
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'client-abc');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'graph.microsoft.com/v1.0/users/itsecurity@globalspace.in/sendMail')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && $request['message']['subject'] === 'Test Subject'
                && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'someone@example.com'
                && str_contains($request['message']['body']['content'], 'Hello from the test suite.');
        });
    }

    public function test_access_token_is_cached_across_multiple_sends(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/*' => Http::response('', 202),
        ]);

        Mail::raw('First message.', fn ($message) => $message->to('one@example.com')->subject('One'));
        Mail::raw('Second message.', fn ($message) => $message->to('two@example.com')->subject('Two'));

        Http::assertSentCount(3); // 1 token request + 2 sendMail requests, not 2 token requests
    }

    public function test_failed_token_request_throws(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(TransportException::class);

        Mail::raw('Should fail.', fn ($message) => $message->to('someone@example.com')->subject('Fail'));
    }
}
