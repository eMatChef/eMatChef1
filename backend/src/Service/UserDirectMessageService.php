<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Direktnachrichten zwischen Mitgliedern eines Departments (Posteingang pro Empfänger).
 * Gespeichert unter inbox.direct_messages.{recipientUserId}.
 */
class UserDirectMessageService
{
    public const SETTING_KEY_PREFIX = 'inbox.direct_messages.';

    public const SENT_KEY_PREFIX = 'inbox.direct_messages.sent.';

    private const MAX_ENTRIES = 200;

    public function __construct(
        private EntityManagerInterface $entityManager,
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
        $entries = $this->readEntries($department->getId(), $recipient->getId());
        $profile = $sender->getProfile();

        $entry = [
            'id' => IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class),
            'type' => 'user_message',
            'sender_user_id' => $sender->getId(),
            'sender_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'sender_first_name' => $profile?->getFirstName(),
            'sender_last_name' => $profile?->getLastName(),
            'sender_nickname' => $profile?->getNickname(),
            'sender_avatar_initials' => $profile?->getAvatarInitials(),
            'sender_background_color' => $profile?->getBackgroundColor(),
            'sender_text_color' => $profile?->getTextColor(),
            'subject' => $subject,
            'message' => $message,
            'created_at' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'read' => false,
        ];

        $entries[] = $entry;
        $this->writeEntries($department, $recipient->getId(), $this->trimEntries($entries));

        $recipientProfile = $recipient->getProfile();
        $sentEntry = array_merge($entry, [
            'recipient_user_id' => $recipient->getId(),
            'recipient_name' => $recipientProfile ? $recipientProfile->getDisplayName() : 'Unbekannt',
            'recipient_first_name' => $recipientProfile?->getFirstName(),
            'recipient_last_name' => $recipientProfile?->getLastName(),
            'recipient_nickname' => $recipientProfile?->getNickname(),
            'recipient_avatar_initials' => $recipientProfile?->getAvatarInitials(),
            'recipient_background_color' => $recipientProfile?->getBackgroundColor(),
            'recipient_text_color' => $recipientProfile?->getTextColor(),
        ]);
        $this->appendSent($department, $sender->getId(), $sentEntry);

        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSent(string $departmentId, string $senderUserId, int $limit = 100): array
    {
        $entries = $this->readSentEntries($departmentId, $senderUserId);
        usort($entries, static fn (array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($entries, 0, max(1, min($limit, 200)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $departmentId, string $recipientUserId, string $bucket = 'all', int $limit = 100): array
    {
        $entries = $this->readEntries($departmentId, $recipientUserId);
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

    public function countUnread(string $departmentId, string $recipientUserId): int
    {
        return count(array_filter(
            $this->readEntries($departmentId, $recipientUserId),
            static fn (array $e): bool => empty($e['read'])
        ));
    }

    public function markRead(string $departmentId, string $recipientUserId, string $messageId): bool
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return false;
        }

        $entries = $this->readEntries($departmentId, $recipientUserId);
        $found = false;
        foreach ($entries as &$entry) {
            if ((string) ($entry['id'] ?? '') === $messageId) {
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

        $this->writeEntries($department, $recipientUserId, $entries);

        return true;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    public function replaceEntries(Department $department, string $recipientUserId, array $entries): void
    {
        $this->writeEntries($department, $recipientUserId, $this->trimEntries($entries));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readEntries(string $departmentId, string $recipientUserId): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::SETTING_KEY_PREFIX . $recipientUserId,
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
    /**
     * @param array<string, mixed> $entry
     */
    private function appendSent(Department $department, string $senderUserId, array $entry): void
    {
        $entries = $this->readSentEntries($department->getId(), $senderUserId);
        $entries[] = $entry;
        $this->writeSentEntries($department, $senderUserId, $this->trimEntries($entries));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSentEntries(string $departmentId, string $senderUserId): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::SENT_KEY_PREFIX . $senderUserId,
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
    private function writeSentEntries(Department $department, string $senderUserId, array $entries): void
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => self::SENT_KEY_PREFIX . $senderUserId,
        ]);

        if (!$setting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::SENT_KEY_PREFIX . $senderUserId);
            $this->entityManager->persist($setting);
        }

        $setting->setSettingValue(json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    private function writeEntries(Department $department, string $recipientUserId, array $entries): void
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => self::SETTING_KEY_PREFIX . $recipientUserId,
        ]);

        if (!$setting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::SETTING_KEY_PREFIX . $recipientUserId);
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
