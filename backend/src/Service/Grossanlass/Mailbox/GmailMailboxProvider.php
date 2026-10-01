<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

use App\Entity\DepartmentGrossanlassGmailAccount;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Grossanlass\GmailOAuthClient;
use App\Service\Grossanlass\GrossanlassGmailApi;
use App\Service\Grossanlass\GrossanlassGmailRouting;

final class GmailMailboxProvider implements GrossanlassMailboxProvider
{
    public function __construct(
        private GmailOAuthClient $oauth,
        private GrossanlassGmailApi $gmail,
    ) {}

    public function id(): string
    {
        return 'gmail';
    }

    public function label(): string
    {
        return 'Gmail';
    }

    public function capabilities(): array
    {
        return ['drafts' => true, 'inbox' => true, 'folders' => true];
    }

    public function isConfigured(): bool
    {
        return $this->oauth->isConfigured();
    }

    public function redirectUri(): string
    {
        return $this->oauth->getRedirectUri();
    }

    public function authorizationUrl(string $state): string
    {
        return $this->oauth->buildAuthorizationUrl($state);
    }

    public function exchangeCode(string $code): array
    {
        return $this->oauth->exchangeCode($code);
    }

    public function revokeRefreshToken(string $refreshToken): void
    {
        $this->oauth->revokeToken($refreshToken);
    }

    public function accessToken(DepartmentGrossanlassGmailAccount $account): string
    {
        return $this->gmail->accessToken($account);
    }

    public function createDraft(
        DepartmentGrossanlassGmailAccount $account,
        string $to,
        string $subject,
        string $body,
        string $inquiryId,
        array $folderIds = [],
        ?string $threadId = null,
        ?string $inReplyTo = null,
        array $attachments = [],
    ): array {
        $token = $this->accessToken($account);
        try {
            return $this->gmail->createDraft(
                $token,
                $to,
                $subject,
                $body,
                $inquiryId,
                $folderIds,
                $threadId,
                $inReplyTo,
                $attachments,
            );
        } catch (GoogleOAuthException $e) {
            if ($folderIds === []) {
                throw $e;
            }
            $draft = $this->gmail->createDraft(
                $token,
                $to,
                $subject,
                $body,
                $inquiryId,
                [],
                $threadId,
                $inReplyTo,
                $attachments,
            );
            if ($draft['threadId'] !== '') {
                try {
                    $this->gmail->modifyThreadLabels($token, $draft['threadId'], $folderIds, []);
                } catch (\Throwable) {
                }
            }

            return $draft;
        }
    }

    public function listThread(DepartmentGrossanlassGmailAccount $account, string $threadId): array
    {
        $token = $this->accessToken($account);
        $out = [];
        foreach ($this->gmail->listThreadMessages($token, $threadId) as $row) {
            $out[] = MailboxMessage::fromGmail($row);
        }

        return $out;
    }

    public function isDraftGone(DepartmentGrossanlassGmailAccount $account, string $draftId): bool
    {
        return $this->gmail->isDraftGone($this->accessToken($account), $draftId);
    }

    public function listRecentSent(DepartmentGrossanlassGmailAccount $account, int $days, int $limit): array
    {
        $days = max(1, $days);

        return $this->gmail->listMessageRefs(
            $this->accessToken($account),
            'in:sent newer_than:' . $days . 'd',
            $limit,
        );
    }

    public function listInboxIds(DepartmentGrossanlassGmailAccount $account, string $folderHint, int $limit): array
    {
        $query = $folderHint !== ''
            ? GrossanlassGmailRouting::inboxQuery($folderHint)
            : 'in:inbox newer_than:21d';

        return $this->gmail->listMessageIds($this->accessToken($account), $query, $limit);
    }

    public function getMessage(DepartmentGrossanlassGmailAccount $account, string $messageId): MailboxMessage
    {
        return MailboxMessage::fromGmail($this->gmail->getMessage($this->accessToken($account), $messageId));
    }

    public function openUrl(?string $threadId, ?string $draftId): ?string
    {
        if ($threadId) {
            return 'https://mail.google.com/mail/u/0/#all/' . $threadId;
        }
        if ($draftId) {
            return 'https://mail.google.com/mail/u/0/#drafts';
        }

        return null;
    }
}
