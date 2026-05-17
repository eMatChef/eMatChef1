<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * In-App-Benachrichtigungen für Benutzer, die per E-Mail in ein Department eingeladen wurden.
 * Gespeichert pro Department unter join.user_notifications.{userId}.
 */
class UserDepartmentInviteNotificationService
{
    public const SETTING_KEY_PREFIX = 'join.user_notifications.';

    private const MAX_ENTRIES_PER_DEPARTMENT = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param array<string, mixed> $pendingInviteEntry
     */
    public function notifyDepartmentInvite(User $user, Department $department, array $pendingInviteEntry): void
    {
        $entries = $this->readEntries($department->getId(), $user->getId());
        $inviteId = (string) ($pendingInviteEntry['id'] ?? '');

        $entries = array_values(array_filter(
            $entries,
            static fn (array $e): bool =>
                ($e['status'] ?? 'pending') !== 'pending'
                || (string) ($e['invite_id'] ?? '') !== $inviteId
        ));

        $inviterId = (string) ($pendingInviteEntry['created_by_user_id'] ?? '');
        $inviterProfile = $this->profileFieldsForUserId($inviterId);

        $entries[] = array_merge([
            'id' => IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class),
            'type' => 'department_invite',
            'invite_id' => $inviteId,
            'department_id' => $department->getId(),
            'department_name' => $department->getName(),
            'invited_by_user_id' => $inviterId,
            'invited_by_name' => (string) ($pendingInviteEntry['created_by_name'] ?? ''),
            'role' => (string) ($pendingInviteEntry['role'] ?? 'u'),
            'invite_url' => (string) ($pendingInviteEntry['invite_url'] ?? ''),
            'created_at' => (string) ($pendingInviteEntry['created_at'] ?? (new \DateTime())->format(\DateTimeInterface::ATOM)),
            'status' => 'pending',
            'read' => false,
        ], $inviterProfile);

        $this->writeEntries($department, $user->getId(), $this->trimEntries($entries));
    }

    public function markInviteAccepted(User $user, Department $department, string $inviteId): void
    {
        $inviteId = trim($inviteId);
        if ($inviteId === '') {
            return;
        }

        $entries = $this->readEntries($department->getId(), $user->getId());
        $changed = false;

        foreach ($entries as &$entry) {
            if ((string) ($entry['invite_id'] ?? '') !== $inviteId) {
                continue;
            }
            $entry['status'] = 'accepted';
            $entry['accepted_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
            $entry['read'] = true;
            $changed = true;
            break;
        }
        unset($entry);

        if ($changed) {
            $this->writeEntries($department, $user->getId(), $entries);
        }
    }

    public function markInviteDeclined(User $user, Department $department, string $inviteId): void
    {
        $inviteId = trim($inviteId);
        if ($inviteId === '') {
            return;
        }

        $entries = $this->readEntries($department->getId(), $user->getId());
        $changed = false;

        foreach ($entries as &$entry) {
            if ((string) ($entry['invite_id'] ?? '') !== $inviteId) {
                continue;
            }
            $entry['status'] = 'declined';
            $entry['declined_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
            $entry['read'] = true;
            $changed = true;
            break;
        }
        unset($entry);

        if ($changed) {
            $this->writeEntries($department, $user->getId(), $entries);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForUser(string $userId, int $limit = 50): array
    {
        return $this->listInboxForUser($userId, 'all', $limit);
    }

    /**
     * Posteingang: Ungelesen / Gelesen / Alle (nur ausstehende Einladungen).
     *
     * @return list<array<string, mixed>>
     */
    public function listInboxForUser(string $userId, string $bucket = 'all', int $limit = 50): array
    {
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
            'settingKey' => self::SETTING_KEY_PREFIX . $userId,
        ]);

        $items = [];
        foreach ($settings as $setting) {
            $raw = trim((string) $setting->getSettingValue());
            if ($raw === '') {
                continue;
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (($entry['status'] ?? 'pending') !== 'pending') {
                        continue;
                    }
                    $items[] = $this->enrichEntry($entry);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $bucket = strtolower(trim($bucket));
        if ($bucket === 'unread') {
            $items = array_values(array_filter($items, static fn (array $e): bool => empty($e['read'])));
        } elseif ($bucket === 'read') {
            $items = array_values(array_filter($items, static fn (array $e): bool => !empty($e['read'])));
        }

        usort($items, static fn (array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($items, 0, max(1, min($limit, 100)));
    }

    public function countUnreadPending(string $userId): int
    {
        return count(array_filter(
            $this->listPendingForUser($userId, 100),
            static fn (array $e): bool => empty($e['read'])
        ));
    }

    public function markRead(string $userId, string $notificationId): bool
    {
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
            'settingKey' => self::SETTING_KEY_PREFIX . $userId,
        ]);

        foreach ($settings as $setting) {
            $entries = $this->decodeEntries((string) $setting->getSettingValue());
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

            if ($found) {
                $setting->setSettingValue(json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
                $setting->setUpdatedAt(new \DateTime());
                $this->entityManager->flush();

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function enrichEntry(array $entry): array
    {
        if (!empty($entry['invited_by_avatar_initials'])) {
            return $entry;
        }

        $inviterId = (string) ($entry['invited_by_user_id'] ?? '');
        if ($inviterId === '') {
            return $entry;
        }

        return array_merge($entry, $this->profileFieldsForUserId($inviterId));
    }

    /**
     * @return array<string, string|null>
     */
    private function profileFieldsForUserId(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return [];
        }

        $profile = $user->getProfile();

        return [
            'invited_by_first_name' => $profile?->getFirstName(),
            'invited_by_last_name' => $profile?->getLastName(),
            'invited_by_nickname' => $profile?->getNickname(),
            'invited_by_avatar_initials' => $profile?->getAvatarInitials(),
            'invited_by_background_color' => $profile?->getBackgroundColor(),
            'invited_by_text_color' => $profile?->getTextColor(),
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

        return $this->decodeEntries((string) $setting->getSettingValue());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeEntries(string $raw): array
    {
        $raw = trim($raw);
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
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => self::SETTING_KEY_PREFIX . $userId,
        ]);

        if (!$setting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::SETTING_KEY_PREFIX . $userId);
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

        return array_slice($entries, 0, self::MAX_ENTRIES_PER_DEPARTMENT);
    }
}
