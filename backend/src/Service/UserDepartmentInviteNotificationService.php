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
        $invites = $this->inboxMessages->listDepartmentInvitesForUser($userId, $bucket, $limit);
        $grossanlass = $this->inboxMessages->listGrossanlassMwAssignedForUser($userId, $bucket, $limit);
        $merged = array_merge($invites, $grossanlass);
        usort($merged, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($merged, 0, max(1, min($limit, 200)));
    }

    public function countUnreadPending(string $userId): int
    {
        return $this->inboxMessages->countUnreadDepartmentInvites($userId)
            + $this->inboxMessages->countUnreadGrossanlassMwAssigned($userId);
    }

    public function markRead(string $userId, string $notificationId): bool
    {
        if ($this->inboxMessages->markDepartmentInviteRead($userId, $notificationId)) {
            return true;
        }

        return $this->inboxMessages->markGrossanlassMwAssignedRead($userId, $notificationId);
    }
}
