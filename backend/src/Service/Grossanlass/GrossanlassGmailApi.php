<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassGmailAccount;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Crypto\SecretBox;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GrossanlassGmailApi
{
    private const BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function __construct(
        private HttpClientInterface $httpClient,
        private GmailOAuthClient $oauth,
        private SecretBox $secrets,
    ) {}

    public function accessToken(DepartmentGrossanlassGmailAccount $account): string
    {
        $expires = $account->getAccessExpiresAt();
        $enc = $account->getAccessTokenEnc();
        if ($enc && $expires instanceof \DateTime && $expires->getTimestamp() > time() + 60) {
            return $this->secrets->decrypt($enc);
        }
        $refresh = $this->secrets->decrypt($account->getRefreshTokenEnc());
        $fresh = $this->oauth->refreshAccessToken($refresh);
        $account->setAccessTokenEnc($this->secrets->encrypt($fresh['access_token']));
        $account->setAccessExpiresAt(new \DateTime('+' . max(60, $fresh['expires_in'] - 30) . ' seconds'));

        return $fresh['access_token'];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listLabels(string $accessToken): array
    {
        $data = $this->get($accessToken, '/labels');
        $out = [];
        foreach ($data['labels'] ?? [] as $label) {
            if (!is_array($label)) {
                continue;
            }
            $id = (string) ($label['id'] ?? '');
            $name = (string) ($label['name'] ?? '');
            if ($id !== '' && $name !== '') {
                $out[] = ['id' => $id, 'name' => $name];
            }
        }

        return $out;
    }

    public function createLabel(string $accessToken, string $name): string
    {
        $data = $this->post($accessToken, '/labels', [
            'name' => $name,
            'labelListVisibility' => 'labelShow',
            'messageListVisibility' => 'show',
        ]);
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Gmail-Label konnte nicht angelegt werden');
        }

        return $id;
    }

    /**
     * @param list<string> $labelIds
     * @return array{draftId: string, threadId: string, messageId: string}
     */
    public function createDraft(
        string $accessToken,
        string $to,
        string $subject,
        string $body,
        string $inquiryId,
        array $labelIds = [],
        ?string $threadId = null,
        ?string $inReplyTo = null,
    ): array {
        $raw = $this->rfc822($to, $subject, $body, $inquiryId, $inReplyTo);
        $message = [
            'raw' => $this->base64url($raw),
        ];
        if ($labelIds !== []) {
            $message['labelIds'] = $labelIds;
        }
        if ($threadId) {
            $message['threadId'] = $threadId;
        }
        $data = $this->post($accessToken, '/drafts', ['message' => $message]);
        $created = is_array($data['message'] ?? null) ? $data['message'] : [];

        return [
            'draftId' => (string) ($data['id'] ?? ''),
            'threadId' => (string) ($created['threadId'] ?? $threadId ?? ''),
            'messageId' => (string) ($created['id'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    public function listMessageIds(string $accessToken, string $query, int $max = 40): array
    {
        $data = $this->get($accessToken, '/messages?q=' . rawurlencode($query) . '&maxResults=' . $max);
        $ids = [];
        foreach ($data['messages'] ?? [] as $message) {
            if (!is_array($message)) {
                continue;
            }
            $id = (string) ($message['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array{
     *     id: string,
     *     threadId: string,
     *     snippet: string,
     *     from: string,
     *     subject: string,
     *     messageIdHeader: string,
     *     internalDate: string,
     *     body: string,
     *     headers: array<string, string>
     * }
     */
    public function getMessage(string $accessToken, string $messageId): array
    {
        $data = $this->get($accessToken, '/messages/' . rawurlencode($messageId) . '?format=full');

        return $this->normalizeMessage($data);
    }

    /**
     * @return list<array{
     *     id: string,
     *     threadId: string,
     *     snippet: string,
     *     from: string,
     *     subject: string,
     *     messageIdHeader: string,
     *     internalDate: string,
     *     body: string,
     *     headers: array<string, string>
     * }>
     */
    public function listThreadMessages(string $accessToken, string $threadId): array
    {
        $data = $this->get($accessToken, '/threads/' . rawurlencode($threadId) . '?format=full');
        $out = [];
        foreach ($data['messages'] ?? [] as $message) {
            if (!is_array($message)) {
                continue;
            }
            $out[] = $this->normalizeMessage($message);
        }

        return $out;
    }

    /**
     * @param list<string> $addLabelIds
     * @param list<string> $removeLabelIds
     */
    public function modifyThreadLabels(
        string $accessToken,
        string $threadId,
        array $addLabelIds,
        array $removeLabelIds,
    ): void {
        if ($addLabelIds === [] && $removeLabelIds === []) {
            return;
        }
        $this->post($accessToken, '/threads/' . rawurlencode($threadId) . '/modify', [
            'addLabelIds' => $addLabelIds,
            'removeLabelIds' => $removeLabelIds,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     id: string,
     *     threadId: string,
     *     snippet: string,
     *     from: string,
     *     subject: string,
     *     messageIdHeader: string,
     *     internalDate: string,
     *     body: string,
     *     headers: array<string, string>
     * }
     */
    private function normalizeMessage(array $data): array
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $headers = GrossanlassGmailInbound::headerMap($payload);
        $body = GrossanlassGmailInbound::extractBody($payload);
        $snippet = trim((string) ($data['snippet'] ?? ''));
        if ($body === '') {
            $body = $snippet;
        }
        $labelIds = [];
        foreach ($data['labelIds'] ?? [] as $label) {
            $id = (string) $label;
            if ($id !== '') {
                $labelIds[] = $id;
            }
        }

        return [
            'id' => (string) ($data['id'] ?? ''),
            'threadId' => (string) ($data['threadId'] ?? ''),
            'snippet' => $snippet,
            'from' => $headers['from'] ?? '',
            'subject' => $headers['subject'] ?? '',
            'messageIdHeader' => $headers['message-id'] ?? '',
            'internalDate' => (string) ($data['internalDate'] ?? ''),
            'body' => $body,
            'headers' => $headers,
            'labelIds' => $labelIds,
        ];
    }

    /**
     * @return list<array{id: string, threadId: string, snippet: string}>
     */
    public function search(string $accessToken, string $query, int $max = 20): array
    {
        $data = $this->get($accessToken, '/messages?q=' . rawurlencode($query) . '&maxResults=' . $max);
        $out = [];
        foreach ($data['messages'] ?? [] as $message) {
            if (!is_array($message)) {
                continue;
            }
            $id = (string) ($message['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $full = $this->get($accessToken, '/messages/' . rawurlencode($id) . '?format=metadata');
            $out[] = [
                'id' => $id,
                'threadId' => (string) ($full['threadId'] ?? $message['threadId'] ?? ''),
                'snippet' => (string) ($full['snippet'] ?? ''),
            ];
        }

        return $out;
    }

    public function isDraftGone(string $accessToken, string $draftId): bool
    {
        try {
            $this->get($accessToken, '/drafts/' . rawurlencode($draftId));

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $accessToken, string $path): array
    {
        return $this->request('GET', $accessToken, $path);
    }

    /**
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    private function post(string $accessToken, string $path, array $json): array
    {
        return $this->request('POST', $accessToken, $path, $json);
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $accessToken, string $path, ?array $json = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }
        try {
            $response = $this->httpClient->request($method, self::BASE . $path, $options);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            throw new GoogleOAuthException('gmail', 'Gmail API: ' . $e->getMessage());
        }
        if ($status >= 400) {
            $message = is_array($data['error'] ?? null)
                ? (string) (($data['error']['message'] ?? '') ?: json_encode($data['error']))
                : 'HTTP ' . $status;
            throw new GoogleOAuthException('gmail', 'Gmail API: ' . $message);
        }

        return $data;
    }

    private function rfc822(
        string $to,
        string $subject,
        string $body,
        string $inquiryId,
        ?string $inReplyTo = null,
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $baseHeaders = [
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'X-eMatChef-Anfrage: ' . $inquiryId,
        ];
        if ($inReplyTo) {
            $baseHeaders[] = 'In-Reply-To: ' . $inReplyTo;
            $baseHeaders[] = 'References: ' . $inReplyTo;
        }
        if (!$this->looksLikeHtml($body)) {
            $headers = array_merge($baseHeaders, [
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ]);

            return implode("\r\n", $headers) . "\r\n\r\n" . $body;
        }

        $boundary = 'emc_' . bin2hex(random_bytes(8));
        $plain = $this->htmlToPlain($body);
        $html = $this->wrapHtml($body);
        $headers = array_merge($baseHeaders, [
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ]);
        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $plain,
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $html,
            '--' . $boundary . '--',
            '',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }

    private function looksLikeHtml(string $body): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $body);
    }

    private function htmlToPlain(string $html): string
    {
        $withBreaks = preg_replace('/<(?:br|\/p|\/div|\/h[1-6]|\/li)\s*\/?>/i', "\n", $html) ?? $html;
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $plain) ?? $plain);
    }

    private function wrapHtml(string $body): string
    {
        if (preg_match('/<html[\s>]/i', $body)) {
            return $body;
        }

        return '<html><body>' . $body . '</body></html>';
    }

    private function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
