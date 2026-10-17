<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassHelper;
use App\Entity\DepartmentGrossanlassHelperAvailability;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Helferpool eines Grossanlasses (V1): Helfer erfassen, ändern, archivieren und ihre Verfügbarkeit in expliziten
 * Zeitfenstern pflegen. Nur GA-HV/GA-SUBHV. Helfer brauchen keinen Login; nur notwendige Personendaten.
 * Nicht zu verwechseln mit {@see GrossanlassHelperService} (Helfer als Benutzerkonto einladen).
 */
final class GrossanlassHelperPoolService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
        private readonly GrossanlassStaffingLocks $locks,
    ) {
    }

    /**
     * @param array{status?: string, q?: string, origin_department_id?: string, limit?: int, offset?: int} $filter
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user, array $filter = []): array
    {
        $this->assertCanManage($department, $user);
        $qb = $this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->createQueryBuilder('h')
            ->where('h.departmentId = :d')->setParameter('d', $department->getId())
            ->orderBy('h.lastName', 'ASC')->addOrderBy('h.firstName', 'ASC')
            ->setMaxResults(max(1, min(500, (int) ($filter['limit'] ?? 200))))
            ->setFirstResult(max(0, (int) ($filter['offset'] ?? 0)));
        $status = (string) ($filter['status'] ?? DepartmentGrossanlassHelper::STATUS_ACTIVE);
        if ($status !== 'all') {
            $qb->andWhere('h.status = :s')->setParameter('s', $status === DepartmentGrossanlassHelper::STATUS_ARCHIVED ? $status : DepartmentGrossanlassHelper::STATUS_ACTIVE);
        }
        if (($filter['origin_department_id'] ?? '') !== '') {
            $qb->andWhere('h.originDepartmentId = :o')->setParameter('o', (string) $filter['origin_department_id']);
        }
        $q = trim((string) ($filter['q'] ?? ''));
        if ($q !== '') {
            $qb->andWhere('LOWER(h.firstName) LIKE :q OR LOWER(h.lastName) LIKE :q OR LOWER(h.email) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(addcslashes($q, '%_\\')) . '%');
        }

        return array_map(fn (DepartmentGrossanlassHelper $h) => $this->serialize($h), $qb->getQuery()->getResult());
    }

    /** @return array<string, mixed> */
    public function get(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);
        $helper = $this->find($department, $helperId);

        return $this->serialize($helper) + ['availability' => $this->availabilityOf($helperId)];
    }

    /**
     * @param array<string, mixed> $data first_name, last_name (mindestens einer), email, phone, note, origin_department_id, source
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertCanManage($department, $user);
        $helper = new DepartmentGrossanlassHelper(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER, DepartmentGrossanlassHelper::class),
            $department->getId(),
        );
        $this->apply($helper, $data, true);
        $this->entityManager->persist($helper);
        $this->entityManager->flush();

        return $this->serialize($helper);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $helperId, array $data): array
    {
        $this->assertCanManage($department, $user);
        $helper = $this->find($department, $helperId);
        $this->apply($helper, $data, false);
        $this->entityManager->flush();

        return $this->serialize($helper);
    }

    /** Archivieren statt löschen; bestehende Zuteilungen bleiben, neue sind nicht mehr möglich. @return array<string, mixed> */
    public function archive(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);

        return $this->locks->transactional(function () use ($department, $helperId): array {
            $helper = $this->locks->lockHelper($department->getId(), $helperId);
            $helper->archive();
            $this->entityManager->flush();

            return $this->serialize($helper);
        });
    }

    /** @return array<string, mixed> */
    public function restore(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);

        return $this->locks->transactional(function () use ($department, $helperId): array {
            $helper = $this->locks->lockHelper($department->getId(), $helperId);
            $helper->restore();
            $this->entityManager->flush();

            return $this->serialize($helper);
        });
    }

    /** @return list<array<string, mixed>> */
    public function listAvailability(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);
        $this->find($department, $helperId);

        return $this->availabilityOf($helperId);
    }

    /**
     * Neues Verfügbarkeitsfenster. Überlappungen mit vorhandenen Fenstern werden abgelehnt (direkt anschliessende sind erlaubt).
     *
     * @return array<string, mixed>
     */
    public function addAvailability(Department $department, User $user, string $helperId, mixed $from, mixed $to): array
    {
        $this->assertCanManage($department, $user);
        $start = GrossanlassStaffingTime::parse($from, 'starts_at');
        $end = GrossanlassStaffingTime::parse($to, 'ends_at');
        GrossanlassStaffingTime::assertWindow($start, $end);

        return $this->locks->transactional(function () use ($department, $helperId, $start, $end): array {
            $this->locks->lockHelper($department->getId(), $helperId);
            $this->assertNoOverlap($helperId, $start, $end, null);
            $row = new DepartmentGrossanlassHelperAvailability(
                GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER_AVAILABILITY, DepartmentGrossanlassHelperAvailability::class),
                $department->getId(),
                $helperId,
                $start,
                $end,
            );
            $this->entityManager->persist($row);
            $this->entityManager->flush();

            return $this->serializeAvailability($row);
        });
    }

    /**
     * Ändert ein Fenster. Aktive Zuteilungen müssen danach weiterhin von der Verfügbarkeit gedeckt sein (sonst Konflikt `in_use`).
     *
     * @return array<string, mixed>
     */
    public function updateAvailability(Department $department, User $user, string $availabilityId, mixed $from, mixed $to): array
    {
        $this->assertCanManage($department, $user);
        $start = GrossanlassStaffingTime::parse($from, 'starts_at');
        $end = GrossanlassStaffingTime::parse($to, 'ends_at');
        GrossanlassStaffingTime::assertWindow($start, $end);

        return $this->locks->transactional(function () use ($department, $availabilityId, $start, $end): array {
            $row = $this->findAvailability($department, $availabilityId);
            $this->locks->lockHelper($department->getId(), $row->getHelperId());
            $this->assertNoOverlap($row->getHelperId(), $start, $end, $row->getId());
            $row->setWindow($start, $end);
            $this->entityManager->flush();
            $this->assertAssignmentsStillCovered($row->getHelperId());

            return $this->serializeAvailability($row);
        });
    }

    public function removeAvailability(Department $department, User $user, string $availabilityId): void
    {
        $this->assertCanManage($department, $user);
        $this->locks->transactional(function () use ($department, $availabilityId): void {
            $row = $this->findAvailability($department, $availabilityId);
            $this->locks->lockHelper($department->getId(), $row->getHelperId());
            $this->entityManager->remove($row);
            $this->entityManager->flush();
            $this->assertAssignmentsStillCovered($row->getHelperId());
        });
    }

    /** @param array<string, mixed> $data */
    private function apply(DepartmentGrossanlassHelper $helper, array $data, bool $creating): void
    {
        $has = static fn (string $k): bool => array_key_exists($k, $data);
        if ($creating || $has('first_name')) {
            $helper->setFirstName($this->text($data['first_name'] ?? '', 100, 'Vorname'));
        }
        if ($creating || $has('last_name')) {
            $helper->setLastName($this->text($data['last_name'] ?? '', 100, 'Nachname'));
        }
        if ($helper->getFirstName() === '' && $helper->getLastName() === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        if ($has('email')) {
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180)) {
                throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
            }
            $helper->setEmail($email === '' ? null : $email);
        }
        if ($has('phone')) {
            $phone = $this->text($data['phone'] ?? '', 40, 'Telefon');
            $helper->setPhone($phone === '' ? null : $phone);
        }
        if ($has('note')) {
            $note = trim((string) ($data['note'] ?? ''));
            if (mb_strlen($note) > 2000) {
                throw new \InvalidArgumentException('Notiz ist zu lang');
            }
            $helper->setNote($note === '' ? null : $note);
        }
        if ($has('origin_department_id')) {
            $origin = trim((string) ($data['origin_department_id'] ?? ''));
            if ($origin !== '' && $this->entityManager->find(Department::class, $origin) === null) {
                throw new \InvalidArgumentException('Herkunfts-Department nicht gefunden');
            }
            $helper->setOriginDepartmentId($origin === '' ? null : $origin);
        }
        if ($creating && $has('source')) {
            $source = (string) $data['source'];
            if (!in_array($source, [DepartmentGrossanlassHelper::SOURCE_MANUAL, DepartmentGrossanlassHelper::SOURCE_IMPORT], true)) {
                throw new \InvalidArgumentException('Ungültige Quelle');
            }
            $helper->setSource($source);
        }
    }

    private function text(mixed $value, int $max, string $label): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) > $max) {
            throw new \InvalidArgumentException($label . ' ist zu lang');
        }

        return $text;
    }

    private function assertNoOverlap(string $helperId, \DateTimeInterface $start, \DateTimeInterface $end, ?string $exceptId): void
    {
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findBy(['helperId' => $helperId]) as $other) {
            if ($other->getId() !== $exceptId && GrossanlassStaffingTime::overlaps($start, $end, $other->getStartsAt(), $other->getEndsAt())) {
                throw new \InvalidArgumentException('Das Fenster überschneidet sich mit einer vorhandenen Verfügbarkeit');
            }
        }
    }

    /** Nach einer Änderung muss jede aktive Zuteilung des Helfers noch von seiner Verfügbarkeit gedeckt sein. */
    private function assertAssignmentsStillCovered(string $helperId): void
    {
        $availability = array_map(
            static fn (DepartmentGrossanlassHelperAvailability $a) => [$a->getStartsAt(), $a->getEndsAt()],
            $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findBy(['helperId' => $helperId]),
        );
        $uncovered = [];
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT id, starts_at, ends_at FROM department_grossanlass_staffing_assignment WHERE helper_id = ? AND status <> 'cancelled'",
            [$helperId],
        );
        foreach ($rows as $row) {
            if (!GrossanlassStaffingTime::isCovered($availability, new \DateTime($row['starts_at']), new \DateTime($row['ends_at']))) {
                $uncovered[] = trim((string) $row['id']);
            }
        }
        if ($uncovered !== []) {
            throw new GrossanlassStaffingConflict(
                'Zuteilungen des Helfers wären nicht mehr durch seine Verfügbarkeit gedeckt',
                GrossanlassStaffingConflict::KIND_IN_USE,
                ['assignment_ids' => $uncovered],
            );
        }
    }

    private function find(Department $department, string $helperId): DepartmentGrossanlassHelper
    {
        $helper = $this->entityManager->find(DepartmentGrossanlassHelper::class, $helperId);
        if (!$helper instanceof DepartmentGrossanlassHelper || $helper->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Helfer nicht gefunden');
        }

        return $helper;
    }

    private function findAvailability(Department $department, string $id): DepartmentGrossanlassHelperAvailability
    {
        $row = $this->entityManager->find(DepartmentGrossanlassHelperAvailability::class, $id);
        if (!$row instanceof DepartmentGrossanlassHelperAvailability || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Verfügbarkeit nicht gefunden');
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function availabilityOf(string $helperId): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)
            ->findBy(['helperId' => $helperId], ['startsAt' => 'ASC']);

        return array_map(fn (DepartmentGrossanlassHelperAvailability $a) => $this->serializeAvailability($a), $rows);
    }

    /** @return array<string, mixed> */
    private function serializeAvailability(DepartmentGrossanlassHelperAvailability $a): array
    {
        return [
            'id' => $a->getId(),
            'helper_id' => $a->getHelperId(),
            'starts_at' => $a->getStartsAt()->format('Y-m-d\TH:i:s'),
            'ends_at' => $a->getEndsAt()->format('Y-m-d\TH:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(DepartmentGrossanlassHelper $h): array
    {
        return [
            'id' => $h->getId(),
            'first_name' => $h->getFirstName(),
            'last_name' => $h->getLastName(),
            'email' => $h->getEmail(),
            'phone' => $h->getPhone(),
            'note' => $h->getNote(),
            'origin_department_id' => $h->getOriginDepartmentId(),
            'has_login' => $h->getUserId() !== null,
            'source' => $h->getSource(),
            'status' => $h->getStatus(),
            'archived_at' => $h->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageStaffing($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Helferpool');
        }
    }
}
