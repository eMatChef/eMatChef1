<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\User;

/**
 * Department-weite Aktivitäts-Meldungen für MW/DC (Persistenz: inbox_message).
 */
class ActivityMwNotificationService
{
    public const SETTING_KEY = 'activity.mw_notifications';

    public function __construct(
        private InboxMessageService $inboxMessages,
    ) {}

    public function notifyActivitySubmitted(Activity $activity, User $actor): void
    {
        $this->inboxMessages->notifyActivitySubmitted($activity, $actor);
    }

    public function notifyActivityReturned(Activity $activity, User $actor): void
    {
        $this->inboxMessages->notifyActivityReturned($activity, $actor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDepartment(string $departmentId, int $limit = 50): array
    {
        return $this->inboxMessages->listActivityMw($departmentId, 'unread', $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $departmentId, string $bucket = 'all', int $limit = 100): array
    {
        return $this->inboxMessages->listActivityMw($departmentId, $bucket, $limit);
    }

    public function countUnread(string $departmentId): int
    {
        return $this->inboxMessages->countUnreadActivityMw($departmentId);
    }

    public function markRead(string $departmentId, string $notificationId): bool
    {
        return $this->inboxMessages->markActivityMwRead($departmentId, $notificationId);
    }
}
