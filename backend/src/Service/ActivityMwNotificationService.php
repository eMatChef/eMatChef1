<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Department-weite Benachrichtigungen für Materialwart/DC (Glocke + Benachrichtigungszentrum).
 */
class ActivityMwNotificationService
{
    public const SETTING_KEY = 'activity.mw_notifications';

    private const MAX_ENTRIES = 200;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * MW/DC informieren: neue Aktivität eingereicht (oder direkt mit Status submitted angelegt).
     */
    public function notifyActivitySubmitted(Activity $activity, User $actor): void
    {
        $department = $activity->getDepartment();
        $entries = $this->readEntries($department->getId());

        $activityId = $activity->getId();
        $entries = array_values(array_filter(
            $entries,
            static fn (array $e): bool =>
                !(
                    ($e['activity_id'] ?? '') === $activityId
                    && ($e['type'] ?? '') === 'activity_submitted'
                    && empty($e['read'])
                )
        ));

        $entries[] = $this->buildEntry($activity, $actor, 'activity_submitted');
        $this->writeEntries($department, $this->trimEntries($entries));
    }

    /**
     * Kurzliste für Glocken-Dropdown (nur ungelesen).
     *
     * @return list<array<string, mixed>>
     */
    public function listForDepartment(string $departmentId, int $limit = 50): array
    {
        return $this->listInbox($departmentId, 'unread', $limit);
    }

    /**
     * Nachrichtenzentrale: Inbox mit Ungelesen/Gelesen/Alle.
     *
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $departmentId, string $bucket = 'all', int $limit = 100): array
    {
        $entries = $this->readEntries($departmentId);
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

    public function countUnread(string $departmentId): int
    {
        $entries = $this->readEntries($departmentId);

        return count(array_filter($entries, static fn (array $e): bool => empty($e['read'])));
    }

    public function markRead(string $departmentId, string $notificationId): bool
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return false;
        }

        $entries = $this->readEntries($departmentId);
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

        $this->writeEntries($department, $entries);

        return true;
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
    private function readEntries(string $departmentId): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::SETTING_KEY,
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
    private function writeEntries(Department $department, array $entries): void
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => self::SETTING_KEY,
        ]);

        if (!$setting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::SETTING_KEY);
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
