<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Service\Clock\BusinessClock;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassTask;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aufbau nur, wenn Material am Standort ist oder jemand den Auftrag gerade abarbeitet.
 */
final class GrossanlassProcurementProgress
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessClock $clock,
    ) {}

    /**
     * @return array<string, 'quoted'|'build'>
     */
    public function buildProgressByGroup(string $departmentId): array
    {
        $now = $this->clock->now($departmentId);
        $progress = [];

        $packs = $this->entityManager->createQueryBuilder()
            ->select('e.groupId AS groupId')
            ->from(DepartmentGrossanlassPack::class, 'p')
            ->innerJoin(DepartmentGrossanlassEinsatz::class, 'e', 'WITH', 'e.id = p.einsatzId')
            ->where('p.departmentId = :departmentId')
            ->andWhere('p.status = :atPlace')
            ->andWhere('e.groupId IS NOT NULL')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('atPlace', DepartmentGrossanlassPack::STATUS_AT_PLACE)
            ->getQuery()
            ->getArrayResult();
        foreach ($packs as $row) {
            if (is_array($row) && !empty($row['groupId'])) {
                $progress[(string) $row['groupId']] = 'build';
            }
        }

        $tasks = $this->entityManager->createQueryBuilder()
            ->select('t.groupId AS groupId, t.startsAt AS startsAt, t.durationMinutes AS durationMinutes')
            ->from(DepartmentGrossanlassTask::class, 't')
            ->where('t.departmentId = :departmentId')
            ->andWhere('t.assigneeUserId IS NOT NULL')
            ->andWhere('t.startsAt IS NOT NULL')
            ->andWhere('t.durationMinutes IS NOT NULL')
            ->andWhere('t.startsAt <= :now')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('now', $now)
            ->getQuery()
            ->getArrayResult();
        foreach ($tasks as $task) {
            if (!is_array($task) || empty($task['groupId']) || !$task['startsAt'] instanceof \DateTimeInterface) {
                continue;
            }
            $end = \DateTime::createFromInterface($task['startsAt']);
            $end->modify('+' . (int) $task['durationMinutes'] . ' minutes');
            if ($now < $end) {
                $progress[(string) $task['groupId']] = 'build';
            }
        }

        $shifts = $this->entityManager->createQueryBuilder()
            ->select('e.groupId AS groupId')
            ->from(DepartmentGrossanlassEinsatz::class, 'e')
            ->where('e.departmentId = :departmentId')
            ->andWhere('e.status = :issued')
            ->andWhere('e.groupId IS NOT NULL')
            ->andWhere('e.startsAt <= :now')
            ->andWhere('e.endsAt >= :now')
            ->andWhere("(e.who <> '' OR e.issuedToUserId IS NOT NULL)")
            ->setParameter('departmentId', $departmentId)
            ->setParameter('issued', DepartmentGrossanlassEinsatz::STATUS_ISSUED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getArrayResult();
        foreach ($shifts as $row) {
            if (is_array($row) && !empty($row['groupId'])) {
                $progress[(string) $row['groupId']] = 'build';
            }
        }

        foreach ($this->quotedGroupIds($departmentId) as $groupId) {
            if (!isset($progress[$groupId])) {
                $progress[$groupId] = 'quoted';
            }
        }

        return $progress;
    }

    /**
     * @return list<string>
     */
    private function quotedGroupIds(string $departmentId): array
    {
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->createQueryBuilder('l')
            ->select('l.id AS id, l.groupId AS groupId, l.status AS status')
            ->where('l.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getArrayResult();
        if ($lines === []) {
            return [];
        }

        $lineIds = [];
        foreach ($lines as $line) {
            if (is_array($line) && isset($line['id'])) {
                $lineIds[] = (string) $line['id'];
            }
        }
        $related = [];
        foreach ([ActivityGrossanlassProcurementQuote::class, ActivityGrossanlassProcurementOrder::class] as $entityClass) {
            $rows = $this->entityManager->createQueryBuilder()
                ->select('DISTINCT e.procurementLineId AS lineId')
                ->from($entityClass, 'e')
                ->where('e.procurementLineId IN (:lineIds)')
                ->setParameter('lineIds', $lineIds)
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['lineId'])) {
                    $related[(string) $row['lineId']] = true;
                }
            }
        }

        $offered = [
            ActivityGrossanlassProcurementLine::STATUS_OFFERTE,
            ActivityGrossanlassProcurementLine::STATUS_BUDGETIERT,
            ActivityGrossanlassProcurementLine::STATUS_BESTELLT,
            ActivityGrossanlassProcurementLine::STATUS_TEILWEISE,
            ActivityGrossanlassProcurementLine::STATUS_ERHALTEN,
        ];
        $groups = [];
        foreach ($lines as $line) {
            if (!is_array($line) || empty($line['groupId'])) {
                continue;
            }
            $status = (string) ($line['status'] ?? '');
            $lineId = (string) ($line['id'] ?? '');
            if (in_array($status, $offered, true) || isset($related[$lineId])) {
                $groups[(string) $line['groupId']] = true;
            }
        }

        return array_keys($groups);
    }
}
