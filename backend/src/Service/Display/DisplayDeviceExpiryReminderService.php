<?php

namespace App\Service\Display;

use App\Entity\Department;
use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\InboxMessage;
use App\Entity\Membership;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\MembershipNotificationEmailResolver;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Erinnerungen vor Ablauf der 90-Tage-Freigabe eines Anzeigegeräts: 14 Tage und 3 Tage vorher.
 * Pro Gerät und Ablaufdatum genau einmal je Stufe; eine Verlängerung ändert das Ablaufdatum und startet damit
 * einen neuen Zyklus. Widerrufene Geräte/Screens und abgelaufene Freigaben werden nicht erinnert.
 * In-App über die bestehende Inbox, E-Mail an die Benachrichtigungsadresse der Mitgliedschaft.
 */
class DisplayDeviceExpiryReminderService
{
    public const STAGE_14 = 14;
    public const STAGE_3 = 3;
    public const TYPE = 'display_approval_expiry';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembershipNotificationEmailResolver $notificationEmails,
        private MailerInterface $mailer,
        private MailOutboundSettingsStore $mailSettings,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $frontendUrl,
    ) {
    }

    /**
     * @return array{devices: int, messages: int}
     */
    public function process(?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTime();
        $horizon = (new \DateTime())->setTimestamp($now->getTimestamp() + self::STAGE_14 * 86400);

        /** @var list<DepartmentDisplayDevice> $devices */
        $devices = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DepartmentDisplayDevice::class, 'd')
            ->where('d.revokedAt IS NULL')
            ->andWhere('d.approvalExpiresAt > :now')
            ->andWhere('d.approvalExpiresAt <= :horizon')
            ->setParameter('now', $now)
            ->setParameter('horizon', $horizon)
            ->getQuery()
            ->getResult();

        $deviceCount = 0;
        $messageCount = 0;
        foreach ($devices as $device) {
            $expires = $device->getApprovalExpiresAt();
            $stage = ($expires->getTimestamp() - $now->getTimestamp()) <= self::STAGE_3 * 86400 ? self::STAGE_3 : self::STAGE_14;
            if ($this->alreadyReminded($device, $stage)) {
                continue;
            }
            $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($device->getScreenId());
            $department = $screen instanceof DepartmentDisplayScreen && !$screen->isRevoked()
                ? $this->entityManager->getRepository(Department::class)->find($screen->getDepartmentId())
                : null;
            if (!$screen instanceof DepartmentDisplayScreen || !$department instanceof Department) {
                continue;
            }

            $messageCount += $this->notify($device, $screen, $department, $stage);
            if ($stage === self::STAGE_3) {
                $device->setReminder3For($expires);
                // Die frühere Stufe entfällt, wenn sie nie verschickt wurde.
                $device->setReminder14For($expires);
            } else {
                $device->setReminder14For($expires);
            }
            ++$deviceCount;
        }
        $this->entityManager->flush();

        return ['devices' => $deviceCount, 'messages' => $messageCount];
    }

    private function alreadyReminded(DepartmentDisplayDevice $device, int $stage): bool
    {
        $marker = $stage === self::STAGE_3 ? $device->getReminder3For() : $device->getReminder14For();

        return $marker !== null && $marker->getTimestamp() === $device->getApprovalExpiresAt()->getTimestamp();
    }

    private function notify(DepartmentDisplayDevice $device, DepartmentDisplayScreen $screen, Department $department, int $stage): int
    {
        // Eindeutig je Gerät, Ablaufdatum und Stufe (Spalte ist auf 32 Zeichen begrenzt).
        $sourceRef = 'dx' . substr(sha1(sprintf('%s:%d:%d', $device->getId(), $device->getApprovalExpiresAt()->getTimestamp(), $stage)), 0, 30);
        $sent = 0;
        $seen = [];

        foreach ($this->entityManager->getRepository(Membership::class)->findBy(['departmentId' => $department->getId()]) as $membership) {
            if (!\in_array(strtolower((string) $membership->getRole()), DepartmentDisplayScreenService::MANAGER_ROLES, true)) {
                continue;
            }
            $user = $membership->getUser();
            $profile = $user?->getProfile();
            if ($user === null || $profile === null || isset($seen[$user->getId()])) {
                continue;
            }
            $seen[$user->getId()] = true;

            $english = str_starts_with(strtolower((string) $profile->getLanguage()), 'en');
            $date = $device->getApprovalExpiresAt()->format('d.m.Y');
            $params = ['device' => $device->getName(), 'screen' => $screen->getName(), 'days' => $stage, 'date' => $date];
            $subject = $english
                ? sprintf('Info display approval expires in %d days: %s', $stage, $params['device'])
                : sprintf('Infoscreen-Freigabe läuft in %d Tagen ab: %s', $stage, $params['device']);
            $body = $english
                ? sprintf("The approval of the device “%s” (info display “%s”) expires on %s. Extend it in the info display settings so the screen keeps running.", $params['device'], $params['screen'], $date)
                : sprintf("Die Freigabe des Geräts «%s» (Infoscreen «%s») läuft am %s ab. Verlängere sie in den Infoscreen-Einstellungen, damit der Bildschirm weiterläuft.", $params['device'], $params['screen'], $date);

            if ($this->entityManager->getRepository(InboxMessage::class)->findOneBy(['sourceRefId' => $sourceRef, 'recipientUserId' => $user->getId()]) === null) {
                $row = new InboxMessage();
                $row->setId(IdGenerator::generateUnique($this->entityManager, InboxMessage::class));
                $row->setDepartment($department);
                $row->setCategory(InboxMessage::CATEGORY_USER_MESSAGE);
                $row->setType(self::TYPE);
                $row->setRecipientScope(InboxMessage::RECIPIENT_USER);
                $row->setRecipientUserId($user->getId());
                $row->setSubject($subject);
                $row->setBody($body);
                $row->setSourceRefId($sourceRef);
                $row->setPayload([
                    'sender_name' => 'eMatChef Infoscreen',
                    'device_id' => $device->getId(),
                    'screen_id' => $screen->getId(),
                    'expires_at' => $device->getApprovalExpiresAt()->format('c'),
                    'stage_days' => $stage,
                ]);
                $this->entityManager->persist($row);
                ++$sent;
            }

            $email = $this->notificationEmails->effectiveEmail($membership);
            if ($email !== '') {
                try {
                    $link = rtrim($this->frontendUrl, '/') . '/' . $department->getId()
                        . ($department->isGrossanlass() ? '/ga/displays' : '/dept/settings/my-department/display-screens');
                    $this->mailer->send((new Email())
                        ->from($this->mailSettings->getFromAddressObject())
                        ->to($email)
                        ->subject($subject)
                        ->text($body . "\n\n" . $link . "\n"));
                } catch (\Throwable $e) {
                    $this->logger->warning('Infoscreen-Erinnerung per E-Mail fehlgeschlagen', ['device_id' => $device->getId(), 'error' => $e->getMessage()]);
                }
            }
        }
        $this->entityManager->flush();

        return $sent;
    }
}
