<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\User;

/**
 * Department-Einladungen in der Inbox (inbox_message).
 */
class UserDepartmentInviteNotificationService
{
    public const SETTING_KEY_PREFIX = 'join.user_notifications.';

    public function __construct(
        private InboxMessageService $inboxMessages,
    ) {}

    /**
     * @param array<string, mixed> $pendingInviteEntry
     */
    public function notifyDepartmentInvite(User $user, Department $department, array $pendingInviteEntry): void
    {
        $this->inboxMessages->notifyDepartmentInvite($user, $department, $pendingInviteEntry);
    }

    public function markInviteAccepted(User $user, Department $department, string $inviteId): void
    {
        $this->inboxMessages->removeDepartmentInvite($department->getId(), $user->getId(), $inviteId);
    }

    public function markInviteDeclined(User $user, Department $department, string $inviteId): void
    {
        $this->inboxMessages->removeDepartmentInvite($department->getId(), $user->getId(), $inviteId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForUser(string $userId, int $limit = 50): array
    {
        return $this->listInboxForUser($userId, 'all', $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInboxForUser(string $userId, string $bucket = 'all', int $limit = 50): array
    {
        return $this->inboxMessages->listDepartmentInvitesForUser($userId, $bucket, $limit);
    }

    public function countUnreadPending(string $userId): int
    {
        return $this->inboxMessages->countUnreadDepartmentInvites($userId);
    }

    public function markRead(string $userId, string $notificationId): bool
    {
        return $this->inboxMessages->markDepartmentInviteRead($userId, $notificationId);
    }
}
