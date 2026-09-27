<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

use App\Entity\DepartmentGrossanlassGmailAccount;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Ein Postfach-Anbieter. Gmail und Outlook 365 erfüllen denselben Vertrag:
 * verbinden, Entwurf ablegen, Verlauf und Posteingang lesen.
 * Ordner/Labels sind eine Fähigkeit, kein Pflichtteil.
 */
#[AutoconfigureTag('app.grossanlass_mailbox')]
interface GrossanlassMailboxProvider
{
    public function id(): string;

    public function label(): string;

    /**
     * @return array{drafts: bool, inbox: bool, folders: bool}
     */
    public function capabilities(): array;

    public function isConfigured(): bool;

    public function redirectUri(): string;

    public function authorizationUrl(string $state): string;

    /**
     * @return array{email: string, access_token: string, refresh_token: ?string, expires_in: int}
     */
    public function exchangeCode(string $code): array;

    public function revokeRefreshToken(string $refreshToken): void;

    public function accessToken(DepartmentGrossanlassGmailAccount $account): string;

    /**
     * @param list<string> $folderIds
     * @param list<array{filename: string, mime: string, content: string}> $attachments
     * @return array{draftId: string, threadId: string, messageId: string}
     */
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
    ): array;

    /**
     * @return list<MailboxMessage>
     */
    public function listThread(DepartmentGrossanlassGmailAccount $account, string $threadId): array;

    public function isDraftGone(DepartmentGrossanlassGmailAccount $account, string $draftId): bool;

    /**
     * @return list<array{id: string, threadId: string}>
     */
    public function listRecentSent(DepartmentGrossanlassGmailAccount $account, int $days, int $limit): array;

    /**
     * @return list<string>
     */
    public function listInboxIds(DepartmentGrossanlassGmailAccount $account, string $folderHint, int $limit): array;

    public function getMessage(DepartmentGrossanlassGmailAccount $account, string $messageId): MailboxMessage;

    public function openUrl(?string $threadId, ?string $draftId): ?string;
}
