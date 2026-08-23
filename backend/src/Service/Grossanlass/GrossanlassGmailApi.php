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
    ): array {
        $raw = $this->rfc822($to, $subject, $body, $inquiryId);
        $payload = [
            'message' => [
                'raw' => $this->base64url($raw),
            ],
        ];
        if ($labelIds !== []) {
            $payload['message']['labelIds'] = $labelIds;
        }
        $data = $this->post($accessToken, '/drafts', $payload);
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];

        return [
            'draftId' => (string) ($data['id'] ?? ''),
            'threadId' => (string) ($message['threadId'] ?? ''),
            'messageId' => (string) ($message['id'] ?? ''),
        ];
    }

    /**
     * @return list<array{id: string, snippet: string, from: string, internalDate: string}>
     */
    public function listThreadMessages(string $accessToken, string $threadId): array
    {
        $data = $this->get($accessToken, '/threads/' . rawurlencode($threadId) . '?format=metadata&metadataHeaders=From');
        $out = [];
        foreach ($data['messages'] ?? [] as $message) {
            if (!is_array($message)) {
                continue;
            }
            $from = '';
            foreach ($message['payload']['headers'] ?? [] as $header) {
                if (is_array($header) && strcasecmp((string) ($header['name'] ?? ''), 'From') === 0) {
                    $from = (string) ($header['value'] ?? '');
                }
            }
            $out[] = [
                'id' => (string) ($message['id'] ?? ''),
                'snippet' => (string) ($message['snippet'] ?? ''),
                'from' => $from,
                'internalDate' => (string) ($message['internalDate'] ?? ''),
            ];
        }

        return $out;
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

    private function rfc822(string $to, string $subject, string $body, string $inquiryId): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-eMatChef-Anfrage: ' . $inquiryId,
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
