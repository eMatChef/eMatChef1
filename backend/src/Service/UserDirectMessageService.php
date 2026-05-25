<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\User;

/**
 * Direktnachrichten zwischen Mitgliedern (Persistenz: inbox_message).
 */
class UserDirectMessageService
{
    public const SETTING_KEY_PREFIX = 'inbox.direct_messages.';

    public const SENT_KEY_PREFIX = 'inbox.direct_messages.sent.';

    public function __construct(
        private InboxMessageService $inboxMessages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function send(
        Department $department,
        User $sender,
        User $recipient,
        string $subject,
        string $message,
    ): array {
        return $this->inboxMessages->sendUserMessage($department, $sender, $recipient, $subject, $message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSent(string $departmentId, string $senderUserId, int $limit = 100): array
    {
        return $this->inboxMessages->listUserSent($departmentId, $senderUserId, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $departmentId, string $recipientUserId, string $bucket = 'all', int $limit = 100): array
    {
        return $this->inboxMessages->listUserInbox($departmentId, $recipientUserId, $bucket, $limit);
    }

    public function countUnread(string $departmentId, string $recipientUserId): int
    {
        return $this->inboxMessages->countUnreadUserMessages($departmentId, $recipientUserId);
    }

    public function markRead(string $departmentId, string $recipientUserId, string $messageId): bool
    {
        return $this->inboxMessages->markUserMessageRead($departmentId, $recipientUserId, $messageId);
    }
}
