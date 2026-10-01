<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

use App\Entity\DepartmentGrossanlassGmailAccount;

final class OutlookMailboxProvider implements GrossanlassMailboxProvider
{
    public function __construct(
        private OutlookOAuthClient $oauth,
        private OutlookGraphApi $graph,
    ) {}

    public function id(): string
    {
        return 'outlook';
    }

    public function label(): string
    {
        return 'Outlook 365';
    }

    public function capabilities(): array
    {
        return ['drafts' => true, 'inbox' => true, 'folders' => false];
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
        unset($refreshToken);
    }

    public function accessToken(DepartmentGrossanlassGmailAccount $account): string
    {
        return $this->graph->accessToken($account);
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
        unset($folderIds, $inReplyTo);

        return $this->graph->createDraft($account, $to, $subject, $body, $inquiryId, $threadId, $attachments);
    }

    public function listThread(DepartmentGrossanlassGmailAccount $account, string $threadId): array
    {
        return $this->graph->listThread($account, $threadId);
    }

    public function isDraftGone(DepartmentGrossanlassGmailAccount $account, string $draftId): bool
    {
        return $this->graph->isDraftGone($account, $draftId);
    }

    public function listRecentSent(DepartmentGrossanlassGmailAccount $account, int $days, int $limit): array
    {
        return $this->graph->listRecentSent($account, $days, $limit);
    }

    public function listInboxIds(DepartmentGrossanlassGmailAccount $account, string $folderHint, int $limit): array
    {
        unset($folderHint);

        return $this->graph->listInboxIds($account, $limit);
    }

    public function getMessage(DepartmentGrossanlassGmailAccount $account, string $messageId): MailboxMessage
    {
        return $this->graph->getMessage($account, $messageId);
    }

    public function openUrl(?string $threadId, ?string $draftId): ?string
    {
        $id = $draftId ?: $threadId;
        if ($id === null || $id === '') {
            return null;
        }

        return 'https://outlook.office.com/mail/deeplink/read/' . rawurlencode($id);
    }
}
