<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\User;

/**
 * Persönliche Aktivitäts-Status-Meldungen (Persistenz: inbox_message).
 */
class ActivityUserNotificationService
{
    public const SETTING_KEY_PREFIX = 'activity.user_notifications.';

    public function __construct(
        private InboxMessageService $inboxMessages,
    ) {}

    public function notifyStatus(Activity $activity, User $actor, string $type): void
    {
        $this->inboxMessages->notifyActivityUserStatus($activity, $actor, $type);
    }

    public function notifyCancelled(Activity $activity, User $actor): void
    {
        $this->inboxMessages->notifyActivityCancelled($activity, $actor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $departmentId, string $userId, string $bucket = 'all', int $limit = 100): array
    {
        return $this->inboxMessages->listActivityUser($departmentId, $userId, $bucket, $limit);
    }

    public function countUnread(string $departmentId, string $userId): int
    {
        return $this->inboxMessages->countUnreadActivityUser($departmentId, $userId);
    }

    public function markRead(string $departmentId, string $userId, string $notificationId): bool
    {
        return $this->inboxMessages->markActivityUserRead($departmentId, $userId, $notificationId);
    }
}
