<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponse;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassProcurementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
    ) {}

    /**
     * @return array{pool: list<array<string, mixed>>, lines: list<array<string, mixed>>}
     */
    public function getBedarfOverview(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        return [
            'pool' => $this->listPoolWishes($department),
            'lines' => $this->listLines($department),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPoolWishes(Department $department): array
    {
        $bundledIds = $this->bundledWishLineIds($department);

        $qb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->innerJoin('w.group', 'g')
            ->where('a.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('w.createdAt', 'DESC');

        if ($bundledIds !== []) {
            $qb->andWhere('w.id NOT IN (:bundledIds)')->setParameter('bundledIds', $bundledIds);
        }

        $lines = $qb->getQuery()->getResult();
        $result = [];
        foreach ($lines as $line) {
            if ($line instanceof ActivityGrossanlassWishLine) {
                $result[] = $this->wishToPoolArray($line);
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLines(Department $department): array
    {
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->where('p.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($lines as $line) {
            if ($line instanceof ActivityGrossanlassProcurementLine) {
                $result[] = $this->lineToArray($line);
            }
        }

        return $result;
    }

    /**
     * @param list<string> $wishLineIds
     *
     * @return array<string, mixed>
     */
    public function createLineFromWishes(Department $department, User $user, array $wishLineIds, array $data = []): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $wishLineIds = array_values(array_unique(array_filter(array_map('strval', $wishLineIds))));
        if ($wishLineIds === []) {
            throw new \InvalidArgumentException('Mindestens ein Wunsch erforderlich');
        }

        $wishes = $this->loadAndValidatePoolWishes($department, $wishLineIds);

        $line = new ActivityGrossanlassProcurementLine();
        $line->setId(IdGenerator::generate12UniqueWithPrefix(
            $this->entityManager,
            ActivityGrossanlassProcurementLine::class,
            'gp',
        ));
        $line->setDepartment($department);
        $line->setCreatedByUser($user);
        $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_BEDARF);

        $this->applyWishAggregation($line, $wishes, $data);

        $this->entityManager->persist($line);
        foreach ($wishes as $wish) {
            $link = new ActivityGrossanlassProcurementLineWish();
            $link->setProcurementLine($line);
            $link->setWishLine($wish);
            $this->entityManager->persist($link);
            $this->markWishAcceptedForProcurement($wish, $user);
        }

        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    /**
     * @param list<string> $wishLineIds
     *
     * @return array<string, mixed>
     */
    public function addWishesToLine(
        Department $department,
        User $user,
        string $lineId,
        array $wishLineIds,
        array $data = [],
    ): array {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $line = $this->findLineInDepartment($department, $lineId);
        if ($line->getStatus() !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            throw new \InvalidArgumentException('Position kann nur im Status «Bedarf» bearbeitet werden');
        }

        $wishLineIds = array_values(array_unique(array_filter(array_map('strval', $wishLineIds))));
        if ($wishLineIds === []) {
            throw new \InvalidArgumentException('Mindestens ein Wunsch erforderlich');
        }

        $newWishes = $this->loadAndValidatePoolWishes($department, $wishLineIds);
        $existingWishes = $this->loadWishesForLine($line);
        $allWishes = array_merge($existingWishes, $newWishes);

        foreach ($newWishes as $wish) {
            $link = new ActivityGrossanlassProcurementLineWish();
            $link->setProcurementLine($line);
            $link->setWishLine($wish);
            $this->entityManager->persist($link);
            $this->markWishAcceptedForProcurement($wish, $user);
        }

        $this->applyWishAggregation($line, $allWishes, $data);
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateLine(Department $department, User $user, string $lineId, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $line = $this->findLineInDepartment($department, $lineId);
        if ($line->getStatus() !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            throw new \InvalidArgumentException('Position kann nur im Status «Bedarf» bearbeitet werden');
        }

        if (isset($data['label'])) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new \InvalidArgumentException('Bezeichnung darf nicht leer sein');
            }
            $line->setLabel($label);
        }
        if (isset($data['quantity'])) {
            $qty = (int) $data['quantity'];
            if ($qty < 1) {
                throw new \InvalidArgumentException('Anzahl muss mindestens 1 sein');
            }
            $line->setQuantity($qty);
        }
        if (isset($data['location'])) {
            $line->setLocation(trim((string) $data['location']));
        }
        if (array_key_exists('notes', $data)) {
            $notes = trim((string) ($data['notes'] ?? ''));
            $line->setNotes($notes === '' ? null : $notes);
        }
        if (!empty($data['group_id'])) {
            $group = $this->findGroupInDepartment($department, (string) $data['group_id']);
            $line->setGroup($group);
        }

        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    public function deleteLine(Department $department, User $user, string $lineId): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $line = $this->findLineInDepartment($department, $lineId);
        if ($line->getStatus() !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            throw new \InvalidArgumentException('Position kann nur im Status «Bedarf» gelöscht werden');
        }

        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['procurementLineId' => $line->getId()]);
        foreach ($links as $link) {
            if ($link instanceof ActivityGrossanlassProcurementLineWish) {
                $this->releaseWishFromProcurement($link->getWishLine(), $user);
            }
            $this->entityManager->remove($link);
        }
        $this->entityManager->remove($line);
        $this->entityManager->flush();
    }

    /**
     * @param list<string> $wishLineIds
     *
     * @return list<ActivityGrossanlassWishLine>
     */
    private function loadAndValidatePoolWishes(Department $department, array $wishLineIds): array
    {
        $bundledIds = array_flip($this->bundledWishLineIds($department));
        $wishes = [];

        foreach ($wishLineIds as $wishId) {
            if (isset($bundledIds[$wishId])) {
                throw new \InvalidArgumentException('Wunsch ist bereits einer Beschaffungsposition zugeordnet');
            }

            $wish = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($wishId);
            if ($wish === null) {
                throw new \InvalidArgumentException('Wunsch nicht gefunden');
            }
            if (!$this->wishBelongsToDepartment($wish, $department)) {
                throw new \InvalidArgumentException('Wunsch gehört nicht zu diesem Grossanlass');
            }
            $wishes[] = $wish;
        }

        return $wishes;
    }

    /**
     * @return list<string>
     */
    private function bundledWishLineIds(Department $department): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->createQueryBuilder('pw')
            ->select('pw.wishLineId')
            ->innerJoin('pw.procurementLine', 'p')
            ->where('p.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r) => (string) $r['wishLineId'], $rows);
    }

    /**
     * @return list<ActivityGrossanlassWishLine>
     */
    private function loadWishesForLine(ActivityGrossanlassProcurementLine $line): array
    {
        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['procurementLineId' => $line->getId()]);

        $wishes = [];
        foreach ($links as $link) {
            if ($link instanceof ActivityGrossanlassProcurementLineWish) {
                $wishes[] = $link->getWishLine();
            }
        }

        return $wishes;
    }

    /**
     * @param list<ActivityGrossanlassWishLine> $wishes
     * @param array<string, mixed>            $overrides
     */
    private function applyWishAggregation(
        ActivityGrossanlassProcurementLine $line,
        array $wishes,
        array $overrides = [],
    ): void {
        if ($wishes === []) {
            throw new \InvalidArgumentException('Keine Wünsche zum Bündeln');
        }

        $first = $wishes[0];
        $totalQty = array_sum(array_map(static fn (ActivityGrossanlassWishLine $w) => $w->getQuantity(), $wishes));

        $label = isset($overrides['label']) && trim((string) $overrides['label']) !== ''
            ? trim((string) $overrides['label'])
            : $first->getLabel();
        if (count($wishes) > 1 && !isset($overrides['label'])) {
            $uniqueLabels = array_unique(array_map(static fn (ActivityGrossanlassWishLine $w) => $w->getLabel(), $wishes));
            if (count($uniqueLabels) > 1) {
                $label = $first->getLabel() . ' (+ ' . (count($wishes) - 1) . ')';
            }
        }

        $quantity = isset($overrides['quantity']) ? (int) $overrides['quantity'] : $totalQty;
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Anzahl muss mindestens 1 sein');
        }

        $location = isset($overrides['location']) && trim((string) $overrides['location']) !== ''
            ? trim((string) $overrides['location'])
            : $first->getLocation();

        $groupId = !empty($overrides['group_id']) ? (string) $overrides['group_id'] : $first->getGroupId();
        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $line->getDepartmentId()) {
            throw new \InvalidArgumentException('Ressort nicht gefunden');
        }

        $kinds = array_unique(array_map(static fn (ActivityGrossanlassWishLine $w) => $w->getWishKind(), $wishes));
        $wishKind = count($kinds) === 1 ? $kinds[0] : ActivityGrossanlassWishLine::KIND_BEIDES;

        $line->setLabel($label);
        $line->setQuantity($quantity);
        $line->setLocation($location);
        $line->setGroup($group);
        $line->setWishKind($wishKind);

        if (array_key_exists('notes', $overrides)) {
            $notes = trim((string) ($overrides['notes'] ?? ''));
            $line->setNotes($notes === '' ? null : $notes);
        }
    }

    private function wishBelongsToDepartment(ActivityGrossanlassWishLine $wish, Department $department): bool
    {
        $activity = $wish->getRound()->getActivity();

        return $activity->getDepartmentId() === $department->getId();
    }

    private function findLineInDepartment(Department $department, string $lineId): ActivityGrossanlassProcurementLine
    {
        $line = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)->find($lineId);
        if ($line === null || $line->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Beschaffungsposition nicht gefunden');
        }

        return $line;
    }

    private function findGroupInDepartment(Department $department, string $groupId): Group
    {
        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ressort nicht gefunden');
        }

        return $group;
    }

    private function markWishAcceptedForProcurement(ActivityGrossanlassWishLine $wish, User $user): void
    {
        if ($wish->getStatus() === ActivityGrossanlassWishLine::STATUS_ACCEPTED) {
            return;
        }

        $wish->setStatus(ActivityGrossanlassWishLine::STATUS_ACCEPTED);
        $wish->touchUpdatedAt();

        $response = $wish->getResponse();
        if ($response instanceof ActivityGrossanlassWishResponse) {
            $response->setStatus(ActivityGrossanlassWishResponse::STATUS_ACCEPTED);
            $response->touchUpdatedAt($user);
        }
    }

    private function releaseWishFromProcurement(ActivityGrossanlassWishLine $wish, User $user): void
    {
        $wish->setStatus(ActivityGrossanlassWishLine::STATUS_REQUESTED);
        $wish->touchUpdatedAt();

        $response = $wish->getResponse();
        if ($response instanceof ActivityGrossanlassWishResponse) {
            $response->setStatus(ActivityGrossanlassWishResponse::STATUS_REQUESTED);
            $response->touchUpdatedAt($user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function wishToPoolArray(ActivityGrossanlassWishLine $wish): array
    {
        $profile = $wish->getCreatedByUser()->getProfile();

        return [
            'id' => $wish->getId(),
            'round_id' => $wish->getRoundId(),
            'round_name' => $wish->getRound()->getName(),
            'group_id' => $wish->getGroupId(),
            'group_name' => $wish->getGroup()->getName(),
            'wish_kind' => $wish->getWishKind(),
            'label' => $wish->getLabel(),
            'quantity' => $wish->getQuantity(),
            'location' => $wish->getLocation(),
            'valid_from' => $wish->getValidFrom()->format(\DateTimeInterface::ATOM),
            'valid_to' => $wish->getValidTo()->format(\DateTimeInterface::ATOM),
            'created_by_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'created_at' => $wish->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineToArray(ActivityGrossanlassProcurementLine $line): array
    {
        $wishIds = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->createQueryBuilder('pw')
            ->select('pw.wishLineId')
            ->where('pw.procurementLineId = :lineId')
            ->setParameter('lineId', $line->getId())
            ->getQuery()
            ->getArrayResult();

        return [
            'id' => $line->getId(),
            'department_id' => $line->getDepartmentId(),
            'group_id' => $line->getGroupId(),
            'group_name' => $line->getGroup()->getName(),
            'wish_kind' => $line->getWishKind(),
            'label' => $line->getLabel(),
            'quantity' => $line->getQuantity(),
            'location' => $line->getLocation(),
            'notes' => $line->getNotes(),
            'status' => $line->getStatus(),
            'wish_line_ids' => array_map(static fn (array $r) => (string) $r['wishLineId'], $wishIds),
            'wish_count' => count($wishIds),
            'created_at' => $line->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $line->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
