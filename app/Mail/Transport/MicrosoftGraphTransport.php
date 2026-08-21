<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends every outgoing app email through the Microsoft Graph `sendMail` API instead of SMTP,
 * always as the one configured mailbox (`mail.mailers.graph.sender`) — this is the mechanism,
 * not a change to *who* mail appears to come from: the app's global `mail.from` address
 * already governs that for every Notification/Mailable (none override it — see
 * App\Notifications\BaseNotification and friends), so "one sender for every notification"
 * was already true before this transport existed.
 *
 * Auth is app-only OAuth2 client-credentials against Entra ID (no user sign-in involved),
 * which requires an Azure AD app registration with the Graph **application** permission
 * `Mail.Send`, admin-consented, and needs the mailbox in `sender` to be allowed to send as
 * itself (true by default for its own mailbox). See config/mail.php's `graph` mailer entry
 * for the four required MS_GRAPH_* env values.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    private const TOKEN_CACHE_KEY = 'ms_graph_mail_access_token';

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $sender,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return "graph://{$this->sender}";
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('The Microsoft Graph transport only supports Symfony Mime Email messages.');
        }

        $response = Http::withToken($this->accessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$this->sender}/sendMail", [
                'message' => $this->messagePayload($email),
                'saveToSentItems' => false,
            ]);

        if ($response->failed()) {
            throw new TransportException("Microsoft Graph sendMail failed ({$response->status()}): {$response->body()}");
        }
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new TransportException("Microsoft Graph token request failed ({$response->status()}): {$response->body()}");
            }

            return (string) $response->json('access_token');
        });
    }

    private function messagePayload(Email $email): array
    {
        return [
            'subject' => $email->getSubject() ?? '',
            'body' => [
                'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
            ],
            'toRecipients' => $this->recipients($email->getTo()),
            'ccRecipients' => $this->recipients($email->getCc()),
            'bccRecipients' => $this->recipients($email->getBcc()),
            'replyTo' => $this->recipients($email->getReplyTo()),
            'attachments' => $this->attachments($email),
        ];
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{emailAddress: array{address: string, name?: string}}>
     */
    private function recipients(array $addresses): array
    {
        return array_map(fn (Address $address) => [
            'emailAddress' => array_filter([
                'address' => $address->getAddress(),
                'name' => $address->getName() ?: null,
            ]),
        ], $addresses);
    }

    /**
     * @return array<int, array{'@odata.type': string, name: string, contentType: string, contentBytes: string}>
     */
    private function attachments(Email $email): array
    {
        return array_map(function (DataPart $part) {
            return [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment',
                'contentType' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'contentBytes' => base64_encode($part->getBody()),
            ];
        }, $email->getAttachments());
    }
}
