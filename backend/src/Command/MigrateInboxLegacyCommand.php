<?php

namespace App\Command;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\InboxMessage;
use App\Entity\PublicFoundItemMessage;
use App\Service\InboxMessageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Migriert JSON-Inbox-Einträge aus department_setting nach inbox_message.
 */
#[AsCommand(
    name: 'app:migrate-inbox-legacy',
    description: 'Migriert Nachrichten aus department_setting (JSON) in die Tabelle inbox_message',
)]
class MigrateInboxLegacyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->entityManager->getRepository(DepartmentSetting::class);
        $settings = $repo->createQueryBuilder('s')
            ->where(
                's.settingKey LIKE :inbox OR s.settingKey = :mw OR s.settingKey LIKE :userAct OR s.settingKey LIKE :deptInv',
            )
            ->setParameter('inbox', 'inbox.direct_messages.%')
            ->setParameter('mw', 'activity.mw_notifications')
            ->setParameter('userAct', 'activity.user_notifications.%')
            ->setParameter('deptInv', 'join.user_notifications.%')
            ->getQuery()
            ->getResult();

        $imported = 0;
        $skipped = 0;

        foreach ($settings as $setting) {
            $key = $setting->getSettingKey();
            if (str_contains($key, '.sent.')) {
                continue;
            }

            $entries = $this->decodeJson($setting->getSettingValue());
            if ($entries === []) {
                continue;
            }

            $department = $setting->getDepartment();
            if (!$department instanceof Department) {
                $department = $this->entityManager->getRepository(Department::class)->find($setting->getDepartmentId());
            }
            if (!$department) {
                continue;
            }

            foreach ($entries as $entry) {
                $id = (string) ($entry['id'] ?? '');
                if ($id === '' || !$this->isValidId($id)) {
                    ++$skipped;
                    continue;
                }
                if ($this->entityManager->find(InboxMessage::class, $id)) {
                    ++$skipped;
                    continue;
                }

                $row = $this->mapLegacyEntry($department, $key, $entry);
                if ($row === null) {
                    ++$skipped;
                    continue;
                }
                $this->entityManager->persist($row);
                ++$imported;
            }
        }

        $this->entityManager->flush();

        $imported += $this->migratePublicFoundTable($skipped);
        $imported += $this->migrateAccountingFollowUps($skipped);
        $imported += $this->migrateInviteAcceptedNotifications($skipped);

        $activities = $this->entityManager->getRepository(Activity::class)->findBy(['deletedAt' => null], null, 500);
        foreach ($activities as $activity) {
            $this->inboxMessages->syncActivityDepartmentInvites($activity);
        }

        $io->success(sprintf(
            '%d Nachrichten importiert, %d übersprungen (bereits vorhanden, Demo-IDs oder ungültige Schlüssel).',
            $imported,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    private function migrateInviteAcceptedNotifications(int &$skipped): int
    {
        $imported = 0;
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
            'settingKey' => 'join.invite_notifications',
        ]);

        foreach ($settings as $setting) {
            $department = $setting->getDepartment();
            if (!$department instanceof Department) {
                $department = $this->entityManager->getRepository(Department::class)->find($setting->getDepartmentId());
            }
            if (!$department) {
                continue;
            }

            foreach ($this->decodeJson($setting->getSettingValue()) as $entry) {
                $id = (string) ($entry['id'] ?? '');
                if ($id === '' || !$this->isValidId($id)) {
                    ++$skipped;
                    continue;
                }
                if ($this->entityManager->find(InboxMessage::class, $id)) {
                    ++$skipped;
                    continue;
                }

                $inviterId = $this->normalizeOptionalId($entry['invited_by_user_id'] ?? null);
                if ($inviterId === null) {
                    ++$skipped;
                    continue;
                }

                $row = new InboxMessage();
                $row->setId($id);
                $row->setDepartment($department);
                $row->setCategory(InboxMessage::CATEGORY_INVITE_ACCEPTED);
                $row->setType('invite_accepted');
                $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
                $row->setRecipientUserId($inviterId);
                $row->setSenderUserId($this->normalizeOptionalId($entry['user_id'] ?? null));
                $row->setSourceRefId('');
                $row->setPayload([
                    'email' => $entry['email'] ?? '',
                    'user_id' => $entry['user_id'] ?? null,
                    'user_name' => $entry['user_name'] ?? '',
                    'invited_by_user_id' => $inviterId,
                    'invited_by_name' => $entry['invited_by_name'] ?? '',
                    'role' => $entry['role'] ?? 'u',
                ]);

                $acceptedAt = isset($entry['accepted_at']) ? strtotime((string) $entry['accepted_at']) : false;
                $row->setCreatedAt($acceptedAt ? (new \DateTime())->setTimestamp($acceptedAt) : new \DateTime());

                if (!empty($entry['read'])) {
                    $row->setReadAt(new \DateTime());
                }

                $this->entityManager->persist($row);
                ++$imported;
            }
        }

        $this->entityManager->flush();

        return $imported;
    }

    private function migratePublicFoundTable(int &$skipped): int
    {
        $imported = 0;
        $rows = $this->entityManager->getRepository(PublicFoundItemMessage::class)->findAll();
        foreach ($rows as $legacy) {
            if ($this->entityManager->find(InboxMessage::class, $legacy->getId())) {
                ++$skipped;
                continue;
            }
            $row = new InboxMessage();
            $row->setId($legacy->getId());
            $row->setDepartment($legacy->getDepartment());
            $row->setCategory(InboxMessage::CATEGORY_QR_FOUND);
            $row->setType('qr_contact');
            $row->setRecipientScope(InboxMessage::RECIPIENT_DEPARTMENT_MW);
            $row->setWorkflowStatus($legacy->getStatus());
            $row->setBody($legacy->getMessage());
            $row->setCreatedAt($legacy->getCreatedAt());
            $row->setReadAt($legacy->getReadAt());
            $row->setReadByUserId($legacy->getReadByUserId());
            $row->setPayload([
                'entity_type' => $legacy->getEntityType(),
                'material_id' => $legacy->getMaterialId(),
                'batch_id' => $legacy->getBatchId(),
                'public_code' => $legacy->getPublicCode(),
                'material_name' => $legacy->getMaterialName(),
                'department_name' => $legacy->getDepartmentName(),
                'serial_line' => $legacy->getSerialLine(),
                'sender_name' => $legacy->getSenderName(),
                'sender_email' => $legacy->getSenderEmail(),
                'public_url' => $legacy->getPublicUrl(),
            ]);
            $this->entityManager->persist($row);
            ++$imported;
        }
        $this->entityManager->flush();

        return $imported;
    }

    private function migrateAccountingFollowUps(int &$skipped): int
    {
        $imported = 0;
        $followUps = $this->entityManager->getRepository(AccountingAcquisitionFollowUp::class)
            ->findBy(['status' => AccountingAcquisitionFollowUp::STATUS_PENDING]);
        foreach ($followUps as $followUp) {
            $existing = $this->entityManager->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from(InboxMessage::class, 'm')
                ->where('m.sourceRefId = :ref')
                ->andWhere('m.category = :cat')
                ->setParameter('ref', $followUp->getId())
                ->setParameter('cat', InboxMessage::CATEGORY_ACCOUNTING_FOLLOWUP)
                ->getQuery()
                ->getSingleScalarResult();
            if ((int) $existing > 0) {
                ++$skipped;
                continue;
            }
            $this->inboxMessages->syncAccountingFollowUp($followUp);
            ++$imported;
        }

        return $imported;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeJson(?string $raw): array
    {
        $raw = trim((string) $raw);
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

    private function isValidId(string $id): bool
    {
        return strlen($id) === 12 && (ctype_xdigit($id) || str_starts_with($id, 'pf'));
    }

    private function normalizeOptionalId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (string) $value;

        return $this->isValidId($id) ? $id : null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function mapLegacyEntry(Department $department, string $settingKey, array $entry): ?InboxMessage
    {
        $row = new InboxMessage();
        $row->setId((string) $entry['id']);
        $row->setDepartment($department);

        if (str_starts_with($settingKey, 'inbox.direct_messages.')) {
            $recipientId = substr($settingKey, strlen('inbox.direct_messages.'));
            if (!$this->isValidId($recipientId)) {
                return null;
            }
            $row->setCategory(InboxMessage::CATEGORY_USER_MESSAGE);
            $row->setType('user_message');
            $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
            $row->setRecipientUserId($recipientId);
            $row->setSenderUserId($this->normalizeOptionalId($entry['sender_user_id'] ?? null));
            $row->setSubject((string) ($entry['subject'] ?? ''));
            $row->setBody((string) ($entry['message'] ?? ''));
            $row->setPayload([
                'sender_name' => $entry['sender_name'] ?? 'Unbekannt',
                'sender_first_name' => $entry['sender_first_name'] ?? null,
                'sender_last_name' => $entry['sender_last_name'] ?? null,
                'sender_nickname' => $entry['sender_nickname'] ?? null,
                'sender_avatar_initials' => $entry['sender_avatar_initials'] ?? null,
                'sender_background_color' => $entry['sender_background_color'] ?? null,
                'sender_text_color' => $entry['sender_text_color'] ?? null,
            ]);
        } elseif ($settingKey === 'activity.mw_notifications') {
            $row->setCategory(InboxMessage::CATEGORY_ACTIVITY_MW);
            $row->setType((string) ($entry['type'] ?? 'activity_submitted'));
            $row->setRecipientScope(InboxMessage::RECIPIENT_DEPARTMENT_MW);
            $row->setActivityId($this->normalizeOptionalId($entry['activity_id'] ?? null));
            $row->setSenderUserId($this->normalizeOptionalId($entry['creator_user_id'] ?? null));
            $row->setPayload($this->activityPayloadFromLegacy($entry));
        } elseif (str_starts_with($settingKey, 'join.user_notifications.')) {
            $recipientId = substr($settingKey, strlen('join.user_notifications.'));
            if (!$this->isValidId($recipientId) || ($entry['status'] ?? 'pending') !== 'pending') {
                return null;
            }
            $row->setCategory(InboxMessage::CATEGORY_DEPARTMENT_INVITE);
            $row->setType('department_invite');
            $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
            $row->setRecipientUserId($recipientId);
            $row->setSenderUserId($this->normalizeOptionalId($entry['invited_by_user_id'] ?? null));
            $row->setSourceRefId((string) ($entry['invite_id'] ?? ''));
            $row->setWorkflowStatus(InboxMessage::WORKFLOW_PENDING);
            $row->setSubject((string) ($entry['department_name'] ?? ''));
            $row->setPayload($entry);
        } elseif (str_starts_with($settingKey, 'activity.user_notifications.')) {
            $recipientId = substr($settingKey, strlen('activity.user_notifications.'));
            if (!$this->isValidId($recipientId)) {
                return null;
            }
            $row->setCategory(InboxMessage::CATEGORY_ACTIVITY_USER);
            $row->setType((string) ($entry['type'] ?? 'activity_approved'));
            $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
            $row->setRecipientUserId($recipientId);
            $row->setActivityId($this->normalizeOptionalId($entry['activity_id'] ?? null));
            $row->setSenderUserId($this->normalizeOptionalId($entry['creator_user_id'] ?? null));
            $row->setPayload($this->activityPayloadFromLegacy($entry));
        } else {
            return null;
        }

        $createdAt = isset($entry['created_at']) ? strtotime((string) $entry['created_at']) : false;
        $row->setCreatedAt($createdAt ? (new \DateTime())->setTimestamp($createdAt) : new \DateTime());

        if (!empty($entry['read']) || !empty($entry['read_at'])) {
            $readAt = isset($entry['read_at']) ? strtotime((string) $entry['read_at']) : false;
            $row->setReadAt($readAt ? (new \DateTime())->setTimestamp($readAt) : new \DateTime());
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function activityPayloadFromLegacy(array $entry): array
    {
        $keys = [
            'activity_name', 'activity_type', 'activity_no', 'activity_status',
            'group_id', 'group_name', 'creator_user_id', 'creator_name',
            'creator_first_name', 'creator_last_name', 'creator_nickname',
            'creator_avatar_initials', 'creator_background_color', 'creator_text_color',
        ];
        $payload = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $entry)) {
                $payload[$key] = $entry[$key];
            }
        }

        return $payload;
    }
}
