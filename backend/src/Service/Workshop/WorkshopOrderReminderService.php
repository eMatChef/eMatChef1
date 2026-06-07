<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\WorkshopTicket;
use App\Service\InboxMessageService;
use Doctrine\ORM\EntityManagerInterface;

final class WorkshopOrderReminderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
    ) {
    }

    public function syncForTicket(WorkshopTicket $ticket): void
    {
        $parts = $ticket->getPartsUsed();
        if (!\is_array($parts)) {
            return;
        }

        foreach ($parts as $line) {
            if (!\is_array($line)) {
                continue;
            }
            $lineId = (string) ($line['id'] ?? '');
            if ($lineId === '') {
                continue;
            }

            $ref = $this->sourceRef($ticket->getId(), $lineId);

            if (($line['source'] ?? '') !== WorkshopPartsUsedValidator::SOURCE_PURCHASE
                || ($line['status'] ?? '') !== WorkshopPartsUsedValidator::STATUS_ORDERED) {
                $this->inboxMessages->removeWorkshopOrderReminderInbox($ref);
                continue;
            }

            $this->inboxMessages->syncWorkshopOrderReminder(
                $ticket->getDepartment(),
                $ref,
                $ticket->getId(),
                $line,
                $this->resolveReminderDate($ticket->getDepartmentId(), $line),
            );
        }
    }

    public function processDueReminders(): int
    {
        return $this->inboxMessages->ensureDueWorkshopOrderReminders();
    }

    /**
     * @param array<string, mixed> $line
     */
    private function resolveReminderDate(string $departmentId, array $line): \DateTime
    {
        $settings = $this->loadWorkshopSettings($departmentId);
        $days = max(1, (int) ($settings['workshop.order_reminder_days'] ?? 7));
        $mode = (string) ($settings['workshop.order_reminder_mode'] ?? 'days');

        $base = new \DateTime();
        if ($mode === 'document_date') {
            $doc = trim((string) ($line['document_date'] ?? ''));
            if ($doc !== '') {
                $parsed = \DateTime::createFromFormat('Y-m-d', $doc);
                if ($parsed instanceof \DateTime) {
                    $base = $parsed;
                }
            }
        } else {
            $orderedAt = trim((string) ($line['ordered_at'] ?? ''));
            if ($orderedAt !== '') {
                try {
                    $base = new \DateTime($orderedAt);
                } catch (\Exception) {
                    // keep now
                }
            }
        }

        $base->modify('+' . $days . ' days');
        $base->setTime(0, 0, 0);

        return $base;
    }

    /**
     * @return array<string, string>
     */
    private function loadWorkshopSettings(string $departmentId): array
    {
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findBy(['departmentId' => $departmentId]);

        $result = DepartmentSetting::getWorkshopDefaults();
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        return $result;
    }

    public function sourceRef(string $ticketId, string $lineId): string
    {
        return $ticketId . ':' . $lineId;
    }
}
