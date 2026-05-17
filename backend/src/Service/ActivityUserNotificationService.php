<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persönliche Aktivitäts-Status-Meldungen (bestätigt, Retour, zurückgewiesen) pro Empfänger.
 */
class ActivityUserNotificationService
{
    public const SETTING_KEY_PREFIX = 'activity.user_notifications.';

    private const MAX_ENTRIES = 200;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function notifyStatus(Activity $activity, User $actor, string $type): void
    {
        $recipient = $this->resolveRecipient($activity);
        if (!$recipient || $recipient->getId() === $actor->getId()) {
            return;
        }

        $department = $activity->getDepartment();
        $entries = $this->readEntries($department->getId(), $recipient->getId());

        $activityId = $activity->getId();
        $entries = array_values(array_filter(
            $entries,
            static fn (array $e): bool =>
                !(
                    ($e['activity_id'] ?? '') === $activityId
                    && ($e['type'] ?? '') === $type
                    && empty($e['read'])
                )
        ));

        $entries[] = $this->buildEntry($activity, $actor, $type);
        $this->writeEntries($department, $recipient->getId(), $this->trimEntries($entries));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $departmentId, string $userId, string $bucket = 'all', int $limit = 100): array
    {
        $entries = $this->readEntries($departmentId, $userId);
        $bucket = strtolower(trim($bucket));
        if ($bucket === 'unread') {
            $entries = array_values(array_filter($entries, static fn (array $e): bool => empty($e['read'])));
        } elseif ($bucket === 'read') {
            $entries = array_values(array_filter($entries, static fn (array $e): bool => !empty($e['read'])));
        }
        usort($entries, static fn (array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($entries, 0, max(1, min($limit, 200)));
    }

    public function countUnread(string $departmentId, string $userId): int
    {
        return count(array_filter(
            $this->readEntries($departmentId, $userId),
            static fn (array $e): bool => empty($e['read'])
        ));
    }

    public function markRead(string $departmentId, string $userId, string $notificationId): bool
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return false;
        }

        $entries = $this->readEntries($departmentId, $userId);
        $found = false;
        foreach ($entries as &$entry) {
            if ((string) ($entry['id'] ?? '') === $notificationId) {
                $entry['read'] = true;
                $entry['read_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
                $found = true;
                break;
            }
        }
        unset($entry);

        if (!$found) {
            return false;
        }

        $this->writeEntries($department, $userId, $entries);

        return true;
    }

    private function resolveRecipient(Activity $activity): ?User
    {
        return $activity->getResponsibleUser() ?? $activity->getCreatedByUser();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEntry(Activity $activity, User $actor, string $type): array
    {
        $profile = $actor->getProfile();
        $group = $activity->getGroup();

        return [
            'id' => IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class),
            'type' => $type,
            'activity_id' => $activity->getId(),
            'activity_name' => $activity->getName(),
            'activity_type' => $activity->getType(),
            'activity_no' => $activity->getNo(),
            'activity_status' => $activity->getStatus(),
            'group_id' => $group?->getId(),
            'group_name' => $group?->getName(),
            'creator_user_id' => $actor->getId(),
            'creator_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'creator_first_name' => $profile?->getFirstName(),
            'creator_last_name' => $profile?->getLastName(),
            'creator_nickname' => $profile?->getNickname(),
            'creator_avatar_initials' => $profile?->getAvatarInitials(),
            'creator_background_color' => $profile?->getBackgroundColor(),
            'creator_text_color' => $profile?->getTextColor(),
            'created_at' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'read' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readEntries(string $departmentId, string $userId): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::SETTING_KEY_PREFIX . $userId,
        ]);
        if (!$setting) {
            return [];
        }

        $raw = trim((string) $setting->getSettingValue());
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function writeEntries(Department $department, string $userId, array $entries): void
    {
        $settingKey = self::SETTING_KEY_PREFIX . $userId;
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => $settingKey,
        ]);

        if (!$setting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey($settingKey);
            $this->entityManager->persist($setting);
        }

        $setting->setSettingValue(json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function trimEntries(array $entries): array
    {
        usort($entries, static fn (array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }
}
