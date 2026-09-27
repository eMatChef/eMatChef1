<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

use App\Entity\DepartmentGrossanlassGmailAccount;
use App\Service\Crypto\SecretBox;
use App\Service\Grossanlass\GrossanlassGmailRouting;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ein Ordner eMatChef (oder eMatChef-Anlass). Kein Label-Baum.
 */
final class OutlookGraphApi
{
    private const BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private HttpClientInterface $httpClient,
        private OutlookOAuthClient $oauth,
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

    public function folderName(DepartmentGrossanlassGmailAccount $account): string
    {
        return GrossanlassGmailRouting::composedRoot($account->getDepartment()->getName());
    }

    public function ensureFolder(DepartmentGrossanlassGmailAccount $account): string
    {
        $name = $this->folderName($account);
        $map = $account->getLabelMap();
        $known = $map[$name] ?? null;
        if (is_string($known) && $known !== '') {
            return $known;
        }
        $token = $this->accessToken($account);
        $id = $this->findFolderId($token, $name) ?? $this->createFolder($token, $name);
        $map[$name] = $id;
        $account->setLabelMap($map);

        return $id;
    }

    /**
     * @param list<array{filename: string, mime: string, content: string}> $attachments
     * @return array{draftId: string, threadId: string, messageId: string}
     */
    public function createDraft(
        DepartmentGrossanlassGmailAccount $account,
        string $to,
        string $subject,
        string $body,
        string $inquiryId,
        ?string $threadId,
        array $attachments,
    ): array {
        $token = $this->accessToken($account);
        $folderId = $this->ensureFolder($account);
        $payload = [
            'subject' => $subject,
            'body' => ['contentType' => 'HTML', 'content' => $body],
            'toRecipients' => [[
                'emailAddress' => ['address' => $to],
            ]],
            'internetMessageHeaders' => [[
                'name' => 'X-eMatChef-Anfrage',
                'value' => $inquiryId,
            ]],
        ];
        if ($threadId) {
            $payload['conversationId'] = $threadId;
        }
        try {
            $created = $this->request('POST', $token, '/me/mailFolders/' . rawurlencode($folderId) . '/messages', $payload);
        } catch (MailboxProviderException $e) {
            unset($payload['internetMessageHeaders'], $payload['conversationId']);
            $created = $this->request('POST', $token, '/me/mailFolders/' . rawurlencode($folderId) . '/messages', $payload);
            unset($e);
        }
        $messageId = (string) ($created['id'] ?? '');
        if ($messageId === '') {
            throw new MailboxProviderException('Outlook-Entwurf konnte nicht angelegt werden.');
        }
        foreach ($attachments as $file) {
            $this->request('POST', $token, '/me/messages/' . rawurlencode($messageId) . '/attachments', [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $file['filename'],
                'contentType' => $file['mime'],
                'contentBytes' => base64_encode($file['content']),
            ]);
        }

        return [
            'draftId' => $messageId,
            'threadId' => (string) ($created['conversationId'] ?? $threadId ?? ''),
            'messageId' => $messageId,
        ];
    }

    /**
     * @return list<MailboxMessage>
     */
    public function listThread(DepartmentGrossanlassGmailAccount $account, string $threadId): array
    {
        $token = $this->accessToken($account);
        $safe = str_replace("'", "''", $threadId);
        $query = rawurlencode("conversationId eq '" . $safe . "'");
        $data = $this->request(
            'GET',
            $token,
            '/me/messages?$filter=' . $query . '&$top=40&$select=id,conversationId,subject,bodyPreview,from,receivedDateTime,isDraft,internetMessageId,body,parentFolderId',
        );
        $out = [];
        foreach ($data['value'] ?? [] as $row) {
            if (is_array($row)) {
                $out[] = $this->toMessage($account, $row);
            }
        }

        return $out;
    }

    public function isDraftGone(DepartmentGrossanlassGmailAccount $account, string $draftId): bool
    {
        $token = $this->accessToken($account);
        try {
            $row = $this->request('GET', $token, '/me/messages/' . rawurlencode($draftId) . '?$select=isDraft');
        } catch (MailboxProviderException) {
            return true;
        }

        return ($row['isDraft'] ?? true) !== true;
    }

    /**
     * @return list<array{id: string, threadId: string}>
     */
    public function listRecentSent(DepartmentGrossanlassGmailAccount $account, int $days, int $limit): array
    {
        $token = $this->accessToken($account);
        $data = $this->request(
            'GET',
            $token,
            '/me/mailFolders/sentitems/messages?$top=' . max(1, $limit) . '&$select=id,conversationId,receivedDateTime&$orderby=receivedDateTime%20desc',
        );
        $cutoff = time() - (max(1, $days) * 86400);
        $out = [];
        foreach ($data['value'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $at = strtotime((string) ($row['receivedDateTime'] ?? ''));
            if ($at !== false && $at < $cutoff) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'threadId' => (string) ($row['conversationId'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function listInboxIds(DepartmentGrossanlassGmailAccount $account, int $limit): array
    {
        $token = $this->accessToken($account);
        $folderId = $this->ensureFolder($account);
        $data = $this->request(
            'GET',
            $token,
            '/me/mailFolders/' . rawurlencode($folderId) . '/messages?$top=' . max(1, $limit) . '&$select=id',
        );
        $out = [];
        foreach ($data['value'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return $out;
    }

    public function getMessage(DepartmentGrossanlassGmailAccount $account, string $messageId): MailboxMessage
    {
        $token = $this->accessToken($account);
        $row = $this->request(
            'GET',
            $token,
            '/me/messages/' . rawurlencode($messageId) . '?$select=id,conversationId,subject,bodyPreview,from,receivedDateTime,isDraft,internetMessageId,body,parentFolderId',
        );

        return $this->toMessage($account, $row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toMessage(DepartmentGrossanlassGmailAccount $account, array $row): MailboxMessage
    {
        $from = '';
        $fromBlock = $row['from']['emailAddress'] ?? null;
        if (is_array($fromBlock)) {
            $from = (string) ($fromBlock['address'] ?? '');
        }
        $body = '';
        $bodyBlock = $row['body'] ?? null;
        if (is_array($bodyBlock)) {
            $body = (string) ($bodyBlock['content'] ?? '');
        }
        $snippet = trim((string) ($row['bodyPreview'] ?? ''));
        $isDraft = ($row['isDraft'] ?? false) === true;
        $isSent = !$isDraft && $from !== '' && strcasecmp($from, $account->getEmail()) === 0;
        $headers = [];
        $internetId = (string) ($row['internetMessageId'] ?? '');
        if ($internetId !== '') {
            $headers['message-id'] = $internetId;
        }

        return new MailboxMessage(
            (string) ($row['id'] ?? ''),
            (string) ($row['conversationId'] ?? ''),
            $from,
            (string) ($row['subject'] ?? ''),
            $body !== '' ? $body : $snippet,
            $snippet,
            $internetId,
            (string) ($row['receivedDateTime'] ?? ''),
            $headers,
            [],
            $isDraft,
            $isSent,
        );
    }

    private function findFolderId(string $token, string $name): ?string
    {
        $next = '/me/mailFolders?$top=100&$select=id,displayName';
        for ($page = 0; $page < 5 && $next !== ''; ++$page) {
            $data = $this->request('GET', $token, $next);
            foreach ($data['value'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (strcasecmp((string) ($row['displayName'] ?? ''), $name) === 0) {
                    $id = (string) ($row['id'] ?? '');

                    return $id !== '' ? $id : null;
                }
            }
            $link = (string) ($data['@odata.nextLink'] ?? '');
            $next = str_starts_with($link, self::BASE) ? substr($link, strlen(self::BASE)) : '';
        }

        return null;
    }

    private function createFolder(string $token, string $name): string
    {
        $created = $this->request('POST', $token, '/me/mailFolders', ['displayName' => $name]);
        $id = (string) ($created['id'] ?? '');
        if ($id === '') {
            throw new MailboxProviderException('Ordner ' . $name . ' konnte in Outlook nicht angelegt werden.');
        }

        return $id;
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $token, string $path, ?array $json = null): array
    {
        $url = str_starts_with($path, 'https://') ? $path : self::BASE . $path;
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Prefer' => 'IdType="ImmutableId"',
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }
        try {
            $response = $this->httpClient->request($method, $url, $options);
            $status = $response->getStatusCode();
            if ($status === 204) {
                return [];
            }
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            throw new MailboxProviderException('Outlook ist nicht erreichbar.');
        }
        if ($status >= 400) {
            $message = '';
            $error = $data['error'] ?? null;
            if (is_array($error)) {
                $message = (string) ($error['message'] ?? '');
            }
            throw new MailboxProviderException($message !== '' ? $message : 'Outlook hat die Anfrage abgelehnt.');
        }

        return $data;
    }
}
