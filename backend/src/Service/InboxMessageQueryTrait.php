<?php

namespace App\Service;

use App\Entity\InboxMessage;
use Doctrine\ORM\QueryBuilder;

/**
 * Gemeinsame Abfragen auf `inbox_message`: laden, zählen und als gelesen markieren.
 *
 * Jede Kategorie ruft dieselben drei Bausteine mit ihrem Empfänger-Filter auf. Der Filter legt die Isolation fest:
 *  - `departmentId`: nur Zeilen dieses Departments
 *  - `userId`: nur Zeilen, die an diese Person adressiert sind
 *  - `recipientScope`: `RECIPIENT_*` (z. B. department_mw)
 *  - `workflowStatus`: Workflow-Zustand (z. B. offene Einladungen)
 * Mindestens `departmentId` oder `userId` gehört in jeden Filter; die Aufrufer übergeben ihn immer.
 *
 * @phpstan-type InboxFilter array{departmentId?: string, userId?: string, recipientScope?: string, workflowStatus?: string}
 * @phpstan-require-extends InboxMessageService
 */
trait InboxMessageQueryTrait
{
    /**
     * @param InboxFilter $filter
     */
    private function inboxQuery(string $category, array $filter): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(InboxMessage::class, 'm')
            ->where('m.category = :cat')
            ->setParameter('cat', $category);

        if (isset($filter['departmentId'])) {
            $qb->andWhere('IDENTITY(m.department) = :deptId')->setParameter('deptId', $filter['departmentId']);
        }
        if (isset($filter['userId'])) {
            $qb->andWhere('m.recipientUserId = :userId')->setParameter('userId', $filter['userId']);
        }
        if (isset($filter['recipientScope'])) {
            $qb->andWhere('m.recipientScope = :scope')->setParameter('scope', $filter['recipientScope']);
        }
        if (isset($filter['workflowStatus'])) {
            $qb->andWhere('m.workflowStatus = :workflow')->setParameter('workflow', $filter['workflowStatus']);
        }

        return $qb;
    }

    /**
     * @param InboxFilter $filter
     *
     * @return list<InboxMessage>
     */
    private function findInboxMessages(string $category, array $filter, string $bucket = 'all', int $limit = 50, int $maxLimit = 100): array
    {
        $qb = $this->inboxQuery($category, $filter)->orderBy('m.createdAt', 'DESC');
        $this->applyReadBucket($qb, $bucket);

        /** @var list<InboxMessage> $rows */
        $rows = $qb->setMaxResults(max(1, min($limit, $maxLimit)))->getQuery()->getResult();

        return $rows;
    }

    /**
     * @param InboxFilter $filter
     */
    private function countUnreadInboxMessages(string $category, array $filter): int
    {
        return (int) $this->inboxQuery($category, $filter)
            ->select('COUNT(m.id)')
            ->andWhere('m.readAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param InboxFilter $filter
     */
    private function findInboxMessage(string $category, array $filter, string $messageId): ?InboxMessage
    {
        $row = $this->inboxQuery($category, $filter)
            ->andWhere('m.id = :id')
            ->setParameter('id', $messageId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row instanceof InboxMessage ? $row : null;
    }

    /**
     * @param InboxFilter $filter
     */
    private function markInboxMessageRead(string $category, array $filter, string $messageId): bool
    {
        $row = $this->findInboxMessage($category, $filter, $messageId);
        if (!$row) {
            return false;
        }
        $row->setReadAt(new \DateTime());
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayloadNotificationArray(InboxMessage $m): array
    {
        return array_merge($m->getPayload(), [
            'id' => $m->getId(),
            'type' => $m->getType(),
            'read' => $m->isRead(),
            'read_at' => $m->getReadAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function applyReadBucket(QueryBuilder $qb, string $bucket): void
    {
        $bucket = strtolower(trim($bucket));
        if ($bucket === 'unread') {
            $qb->andWhere('m.readAt IS NULL');
        } elseif ($bucket === 'read') {
            $qb->andWhere('m.readAt IS NOT NULL');
        }
    }
}
