<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\PublicFoundItemMessage;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Musterdaten für die Nachrichtenzentrale (Vorschau / Demo).
 */
class InboxDemoSeedService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserDirectMessageService $directMessages,
        private ActivityMwNotificationService $activityMwNotifications,
    ) {}

    /**
     * @return array<string, int>
     */
    public function seedForUser(Department $department, User $recipient, bool $includeMw, bool $includeQr): array
    {
        $deptId = $department->getId();
        $recipientId = $recipient->getId();
        $now = new \DateTime();

        $userMessages = $this->buildSampleUserMessages($now);
        $this->directMessages->replaceEntries($department, $recipientId, $userMessages);
        $counts = ['user_messages' => count($userMessages)];

        if ($includeMw) {
            $counts['activity_mw'] = $this->seedActivityMw($department, $now);
        }

        if ($includeQr) {
            $counts['qr_found'] = $this->seedQrMessages($department, $now);
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSampleUserMessages(\DateTime $now): array
    {
        $t1 = (clone $now)->modify('-25 minutes')->format(\DateTimeInterface::ATOM);
        $t2 = (clone $now)->modify('-3 hours')->format(\DateTimeInterface::ATOM);
        $t3 = (clone $now)->modify('-2 days')->format(\DateTimeInterface::ATOM);

        return [
            [
                'id' => 'demo-um-001',
                'type' => 'user_message',
                'sender_user_id' => 'demo-sender-01',
                'sender_name' => 'Lara Meier',
                'sender_first_name' => 'Lara',
                'sender_last_name' => 'Meier',
                'sender_nickname' => 'Lara',
                'sender_avatar_initials' => 'LM',
                'sender_background_color' => '#EC4899',
                'sender_text_color' => '#FFFFFF',
                'subject' => 'Kannst du beim Samstag-Packen helfen?',
                'message' => 'Hoi! Wir sind mit der Gruppe Wolf kurz unterbesetzt. Hättest du am Samstag vormittag 2h Zeit im Magazin?',
                'created_at' => $t1,
                'read' => false,
            ],
            [
                'id' => 'demo-um-002',
                'type' => 'user_message',
                'sender_user_id' => 'demo-sender-02',
                'sender_name' => 'Tim Fischer',
                'sender_first_name' => 'Tim',
                'sender_last_name' => 'Fischer',
                'sender_avatar_initials' => 'TF',
                'sender_background_color' => '#2563EB',
                'sender_text_color' => '#FFFFFF',
                'subject' => 'Material Rückgabe Lager 3',
                'message' => 'Die Kochgruppe hat gestern spät zurückgegeben. Bitte kurz prüfen, ob alles vollständig ist.',
                'created_at' => $t2,
                'read' => false,
            ],
            [
                'id' => 'demo-um-003',
                'type' => 'user_message',
                'sender_user_id' => 'demo-sender-03',
                'sender_name' => 'Sandra Huber',
                'sender_first_name' => 'Sandra',
                'sender_last_name' => 'Huber',
                'sender_avatar_initials' => 'SH',
                'sender_background_color' => '#0D9488',
                'sender_text_color' => '#FFFFFF',
                'subject' => 'Danke für die schnelle Rückmeldung',
                'message' => 'Hat super geklappt mit der Reservierung — danke dir!',
                'created_at' => $t3,
                'read' => true,
                'read_at' => (clone $now)->modify('-1 day')->format(\DateTimeInterface::ATOM),
            ],
        ];
    }

    private function seedActivityMw(Department $department, \DateTime $now): int
    {
        $entries = [
            [
                'id' => 'demo-amw-001',
                'type' => 'activity_submitted',
                'activity_id' => 'demo-activity-001',
                'activity_name' => 'Sommerlager Pfadi Nord',
                'activity_type' => 'camp',
                'activity_no' => 12,
                'activity_status' => 'submitted',
                'group_id' => null,
                'group_name' => 'Gruppe Wolf',
                'creator_user_id' => 'demo-sender-01',
                'creator_name' => 'Lara Meier',
                'creator_first_name' => 'Lara',
                'creator_last_name' => 'Meier',
                'creator_nickname' => 'Lara',
                'creator_avatar_initials' => 'LM',
                'creator_background_color' => '#EC4899',
                'creator_text_color' => '#FFFFFF',
                'created_at' => (clone $now)->modify('-45 minutes')->format(\DateTimeInterface::ATOM),
                'read' => false,
            ],
            [
                'id' => 'demo-amw-002',
                'type' => 'activity_submitted',
                'activity_id' => 'demo-activity-002',
                'activity_name' => 'Weekend Ski Tour',
                'activity_type' => 'event',
                'activity_no' => 7,
                'activity_status' => 'submitted',
                'group_name' => 'Gruppe Bär',
                'creator_user_id' => 'demo-sender-02',
                'creator_name' => 'Tim Fischer',
                'creator_first_name' => 'Tim',
                'creator_last_name' => 'Fischer',
                'creator_avatar_initials' => 'TF',
                'creator_background_color' => '#2563EB',
                'creator_text_color' => '#FFFFFF',
                'created_at' => (clone $now)->modify('-6 hours')->format(\DateTimeInterface::ATOM),
                'read' => true,
                'read_at' => (clone $now)->modify('-5 hours')->format(\DateTimeInterface::ATOM),
            ],
        ];

        $settingKey = ActivityMwNotificationService::SETTING_KEY;
        $this->writeDepartmentJsonSetting($department, $settingKey, $entries);

        return count($entries);
    }

    private function seedQrMessages(Department $department, \DateTime $now): int
    {
        $repo = $this->entityManager->getRepository(PublicFoundItemMessage::class);
        $existing = $repo->findBy(['department' => $department], ['createdAt' => 'DESC'], 5);
        foreach ($existing as $old) {
            if (str_starts_with((string) $old->getPublicCode(), 'DEMO-QR-')) {
                $this->entityManager->remove($old);
            }
        }

        $samples = [
            [
                'code' => 'DEMO-QR-001',
                'material' => 'Zelt Odyssey 6P',
                'message' => 'Hallo, wir haben euer Zelt am Parkplatz gefunden. Liegt bei der Abfalltonne.',
                'sender' => 'Passant',
                'email' => 'fund@beispiel.ch',
                'status' => PublicFoundItemMessage::STATUS_OPEN,
                'at' => (clone $now)->modify('-20 minutes'),
            ],
            [
                'code' => 'DEMO-QR-002',
                'material' => 'Kochset Gruppe',
                'message' => 'Ist das Kochset noch verfügbar? Wir würden es fürs Wochenende brauchen.',
                'sender' => 'Elternvertretung',
                'email' => null,
                'status' => PublicFoundItemMessage::STATUS_IN_PROGRESS,
                'at' => (clone $now)->modify('-1 day'),
            ],
        ];

        $count = 0;
        foreach ($samples as $s) {
            $msg = new PublicFoundItemMessage();
            $msg->setId(IdGenerator::generateUnique($this->entityManager, PublicFoundItemMessage::class));
            $msg->setDepartment($department);
            $msg->setEntityType('material');
            $msg->setMaterialId(null);
            $msg->setPublicCode($s['code']);
            $msg->setMaterialName($s['material']);
            $msg->setDepartmentName($department->getName());
            $msg->setMessage($s['message']);
            $msg->setSenderName($s['sender']);
            $msg->setSenderEmail($s['email']);
            $msg->setPublicUrl('/public/material/' . $s['code']);
            $msg->setStatus($s['status']);
            $msg->setCreatedAt($s['at']);
            if ($s['status'] !== PublicFoundItemMessage::STATUS_OPEN) {
                $msg->setReadAt($s['at']);
            }
            $this->entityManager->persist($msg);
            ++$count;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function writeDepartmentJsonSetting(Department $department, string $settingKey, array $entries): void
    {
        $setting = $this->entityManager->getRepository(\App\Entity\DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => $settingKey,
        ]);

        if (!$setting) {
            $setting = new \App\Entity\DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, \App\Entity\DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey($settingKey);
            $this->entityManager->persist($setting);
        }

        $setting->setSettingValue(json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }
}
