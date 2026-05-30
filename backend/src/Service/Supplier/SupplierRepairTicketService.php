<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\SupplierCompany;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Repository\SupplierCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lieferanten-Sicht auf zugewiesene Workshop-Tickets (minimaler Datenzugriff).
 */
class SupplierRepairTicketService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCompanyRepository $companyRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTickets(string $companyId, ?string $status = null): array
    {
        $this->requireCompany($companyId);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('t', 'm', 'd')
            ->from(WorkshopTicket::class, 't')
            ->innerJoin('t.materialItem', 'm')
            ->innerJoin('t.department', 'd')
            ->where('t.assignedToSupplierCompanyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('t.updatedAt', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        $items = [];
        foreach ($qb->getQuery()->getResult() as $ticket) {
            if (!$ticket instanceof WorkshopTicket) {
                continue;
            }
            $items[] = $this->serializeTicket($ticket, false);
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function getTicket(string $companyId, string $ticketId): array
    {
        $ticket = $this->requireAssignedTicket($companyId, $ticketId);

        return $this->serializeTicket($ticket, true);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function updateTicket(string $companyId, string $ticketId, array $data): array
    {
        $ticket = $this->requireAssignedTicket($companyId, $ticketId);

        if (\array_key_exists('estimated_cost', $data)) {
            $ticket->setEstimatedCost($this->nullableDecimalString($data['estimated_cost']));
        }
        if (\array_key_exists('actual_cost', $data)) {
            $ticket->setActualCost($this->nullableDecimalString($data['actual_cost']));
        }
        if (\array_key_exists('photos', $data) && \is_array($data['photos'])) {
            $ticket->setPhotos(array_values(array_map('strval', $data['photos'])));
        }
        if (\array_key_exists('resolution_notes', $data)) {
            $ticket->setResolutionNotes($this->nullableString($data['resolution_notes']));
        }

        $ticket->updateTimestamps();
        $this->entityManager->flush();

        return $this->serializeTicket($ticket, true);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function transitionTicket(string $companyId, string $ticketId, array $data): array
    {
        $ticket = $this->requireAssignedTicket($companyId, $ticketId);
        $newStatus = trim((string) ($data['status'] ?? ''));

        if ($newStatus === '') {
            throw new \InvalidArgumentException('status ist erforderlich');
        }
        if (!\in_array($newStatus, WorkshopTicket::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException('Ungültiger status');
        }
        if (!$ticket->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException('Statusübergang nicht erlaubt');
        }

        if ($newStatus === WorkshopTicket::STATUS_WAITING_PARTS) {
            $cost = \array_key_exists('estimated_cost', $data)
                ? $this->nullableDecimalString($data['estimated_cost'])
                : $ticket->getEstimatedCost();
            if ($cost === null || $cost === '') {
                throw new \InvalidArgumentException('estimated_cost ist für Kostenvoranschlag erforderlich');
            }
            $ticket->setEstimatedCost($cost);
        }

        if ($newStatus === WorkshopTicket::STATUS_COMPLETED) {
            $resolutionAction = (string) ($data['resolution_action'] ?? 'repaired');
            if (\in_array($resolutionAction, ['repaired', 'writeoff'], true)) {
                $actual = \array_key_exists('actual_cost', $data)
                    ? $this->nullableDecimalString($data['actual_cost'])
                    : $ticket->getActualCost();
                if ($actual === null || $actual === '') {
                    throw new \InvalidArgumentException('actual_cost ist beim Abschluss erforderlich');
                }
                $ticket->setActualCost($actual);
            }
            if (\array_key_exists('resolution_notes', $data)) {
                $ticket->setResolutionNotes($this->nullableString($data['resolution_notes']));
            }
            if (isset($data['resolution_action'])) {
                $ticket->setResolutionAction((string) $data['resolution_action']);
            }
        }

        $now = new \DateTime();
        if ($newStatus === WorkshopTicket::STATUS_IN_PROGRESS && !$ticket->getStartedAt()) {
            $ticket->setStartedAt($now);
        }
        if ($newStatus === WorkshopTicket::STATUS_COMPLETED) {
            $ticket->setCompletedAt($now);
        }

        $ticket->setStatus($newStatus);
        $ticket->updateTimestamps();
        $this->entityManager->flush();

        return $this->serializeTicket($ticket, true);
    }

    private function requireCompany(string $companyId): SupplierCompany
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof SupplierCompany) {
            throw new \InvalidArgumentException('Supplier-Firma nicht gefunden');
        }

        return $company;
    }

    private function requireAssignedTicket(string $companyId, string $ticketId): WorkshopTicket
    {
        $ticket = $this->entityManager->createQueryBuilder()
            ->select('t', 'm', 'd', 'ir')
            ->from(WorkshopTicket::class, 't')
            ->innerJoin('t.materialItem', 'm')
            ->innerJoin('t.department', 'd')
            ->leftJoin('t.issueReport', 'ir')
            ->where('t.id = :ticketId')
            ->andWhere('t.assignedToSupplierCompanyId = :companyId')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ticket instanceof WorkshopTicket) {
            throw new \InvalidArgumentException('Ticket nicht gefunden oder nicht zugewiesen');
        }

        return $ticket;
    }

    /** @return array<string, mixed> */
    private function serializeTicket(WorkshopTicket $ticket, bool $detailed): array
    {
        $material = $ticket->getMaterialItem();
        $department = $ticket->getDepartment();
        $issueReport = $ticket->getIssueReport();

        $result = [
            'id' => $ticket->getId(),
            'type' => $ticket->getType(),
            'type_label' => $ticket->getTypeLabel(),
            'priority' => $ticket->getPriority(),
            'priority_label' => $ticket->getPriorityLabel(),
            'status' => $ticket->getStatus(),
            'status_label' => $ticket->getStatusLabel(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'estimated_cost' => $ticket->getEstimatedCost(),
            'actual_cost' => $ticket->getActualCost(),
            'resolution_action' => $ticket->getResolutionAction(),
            'resolution_notes' => $ticket->getResolutionNotes(),
            'started_at' => $ticket->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'completed_at' => $ticket->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $ticket->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $ticket->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'allowed_transitions' => WorkshopTicket::STATUS_TRANSITIONS[$ticket->getStatus()] ?? [],
            'material_item' => [
                'id' => $material->getId(),
                'name' => $material->getName(),
                'condition' => $material->getCondition(),
                'serial_number' => $this->resolveMaterialSerial($material->getId()),
            ],
            'department' => [
                'id' => $department->getId(),
                'name' => $department->getName(),
            ],
        ];

        if ($issueReport !== null) {
            $result['issue_report'] = [
                'type' => $issueReport->getType(),
                'type_label' => $issueReport->getTypeLabel(),
                'description' => $issueReport->getDescription(),
                'photo_url' => $issueReport->getPhotoUrl(),
                'reported_at' => $issueReport->getReportedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        if ($detailed) {
            $result['photos'] = $ticket->getPhotos();
            $createdBy = $ticket->getCreatedByUser();
            if ($createdBy instanceof User) {
                $result['department_contact'] = [
                    'name' => $this->displayUserName($createdBy),
                ];
            }
        }

        return $result;
    }

    private function resolveMaterialSerial(string $materialItemId): ?string
    {
        $batch = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(MaterialBatch::class, 'b')
            ->where('b.materialItemId = :materialId')
            ->andWhere('b.serialNumber IS NOT NULL')
            ->setParameter('materialId', $materialItemId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$batch instanceof MaterialBatch) {
            return null;
        }

        return $batch->getSerialNumber();
    }

    private function displayUserName(User $user): string
    {
        $profile = $user->getProfile();
        if ($profile && trim($profile->getDisplayName()) !== '') {
            return trim($profile->getDisplayName());
        }

        return trim($user->getEmail() ?? 'Materialwart');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableDecimalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
