<?php

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\User;
use App\Util\IdGenerator;
use App\Service\GroupHierarchyService;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassWishService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassGroupService $groupService,
        private GrossanlassPlanningRoundService $roundService,
        private GroupHierarchyService $hierarchy,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listWishes(Department $department, User $user, string $roundId, ?string $groupIdFilter = null): array
    {
        $round = $this->roundService->findRoundForDepartment($department, $roundId);
        $qb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->where('w.roundId = :roundId')
            ->setParameter('roundId', $round->getId())
            ->orderBy('w.createdAt', 'DESC');

        if ($groupIdFilter !== null && $groupIdFilter !== '') {
            $qb->andWhere('w.groupId = :groupId')->setParameter('groupId', $groupIdFilter);
        }

        $lines = $qb->getQuery()->getResult();
        $allowedGroupIds = $this->resolveVisibleGroupIds($department, $user);

        return array_values(array_filter(
            array_map(fn (ActivityGrossanlassWishLine $w) => $this->toArray($w),
                array_filter(
                    $lines,
                    fn ($w) => $w instanceof ActivityGrossanlassWishLine
                        && ($allowedGroupIds === null || in_array($w->getGroupId(), $allowedGroupIds, true)),
                ),
            ),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWishesForUserRessort(Department $department, User $user): array
    {
        $allowedGroupIds = $this->resolveVisibleGroupIds($department, $user);
        if ($allowedGroupIds === []) {
            return [];
        }

        $lines = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('w.groupId IN (:groupIds)')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('groupIds', $allowedGroupIds)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (ActivityGrossanlassWishLine $w) => $this->toArray($w), $lines);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createWish(Department $department, User $user, string $roundId, array $data): array
    {
        $round = $this->roundService->findRoundForDepartment($department, $roundId);
        if ($round->getStatus() !== ActivityGrossanlassRound::STATUS_OPEN) {
            throw new \InvalidArgumentException('Wünsche nur in offenen Runden möglich');
        }

        $group = $this->resolveGroupForCreate($department, $user, $data);

        $wishKind = (string) ($data['wish_kind'] ?? '');
        if (!in_array($wishKind, [
            ActivityGrossanlassWishLine::KIND_MATERIAL,
            ActivityGrossanlassWishLine::KIND_FAHRZEUG,
            ActivityGrossanlassWishLine::KIND_BEIDES,
        ], true)) {
            throw new \InvalidArgumentException('wish_kind ungültig');
        }

        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
        }

        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Anzahl muss mindestens 1 sein');
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location === '') {
            throw new \InvalidArgumentException('Ort ist erforderlich');
        }

        $validFrom = $this->parseDateTime($data['valid_from'] ?? null, 'valid_from');
        $validTo = $this->parseDateTime($data['valid_to'] ?? null, 'valid_to');
        if ($validTo < $validFrom) {
            throw new \InvalidArgumentException('Zeitraum Ende muss nach Start liegen');
        }

        if (!$this->canWriteWishForGroup($department, $user, $group)) {
            throw new \RuntimeException('Keine Berechtigung für dieses Ressort/Bauprojekt');
        }

        $line = new ActivityGrossanlassWishLine();
        $line->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, ActivityGrossanlassWishLine::class, 'gw'));
        $line->setRound($round);
        $line->setGroup($group);
        $line->setWishKind($wishKind);
        $line->setLabel($label);
        $line->setQuantity($quantity);
        $line->setLocation($location);
        $line->setValidFrom($validFrom);
        $line->setValidTo($validTo);
        $line->setTimeframeNotes($this->optionalString($data['timeframe_notes'] ?? null));
        $line->setNotes($this->optionalString($data['notes'] ?? null));
        $line->setCreatedByUser($user);

        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $this->toArray($line);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateWish(Department $department, User $user, string $roundId, string $wishId, array $data): array
    {
        $line = $this->findWishInRound($department, $roundId, $wishId);
        $this->assertCanEditWish($department, $user, $line);

        if (isset($data['wish_kind'])) {
            $wishKind = (string) $data['wish_kind'];
            if (!in_array($wishKind, [
                ActivityGrossanlassWishLine::KIND_MATERIAL,
                ActivityGrossanlassWishLine::KIND_FAHRZEUG,
                ActivityGrossanlassWishLine::KIND_BEIDES,
            ], true)) {
                throw new \InvalidArgumentException('wish_kind ungültig');
            }
            $line->setWishKind($wishKind);
        }
        if (isset($data['label'])) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
            }
            $line->setLabel($label);
        }
        if (isset($data['quantity'])) {
            $quantity = (int) $data['quantity'];
            if ($quantity < 1) {
                throw new \InvalidArgumentException('Anzahl muss mindestens 1 sein');
            }
            $line->setQuantity($quantity);
        }
        if (isset($data['location'])) {
            $location = trim((string) $data['location']);
            if ($location === '') {
                throw new \InvalidArgumentException('Ort ist erforderlich');
            }
            $line->setLocation($location);
        }
        if (array_key_exists('valid_from', $data)) {
            $line->setValidFrom($this->parseDateTime($data['valid_from'], 'valid_from'));
        }
        if (array_key_exists('valid_to', $data)) {
            $line->setValidTo($this->parseDateTime($data['valid_to'], 'valid_to'));
        }
        if ($line->getValidTo() < $line->getValidFrom()) {
            throw new \InvalidArgumentException('Zeitraum Ende muss nach Start liegen');
        }
        if (array_key_exists('timeframe_notes', $data)) {
            $line->setTimeframeNotes($this->optionalString($data['timeframe_notes']));
        }
        if (array_key_exists('notes', $data)) {
            $line->setNotes($this->optionalString($data['notes']));
        }

        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->toArray($line);
    }

    public function deleteWish(Department $department, User $user, string $roundId, string $wishId): void
    {
        $line = $this->findWishInRound($department, $roundId, $wishId);
        $this->assertCanEditWish($department, $user, $line);
        $this->entityManager->remove($line);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveGroupForCreate(Department $department, User $user, array $data): Group
    {
        $newBauprojekt = $data['new_bauprojekt'] ?? null;
        if (is_array($newBauprojekt) && trim((string) ($newBauprojekt['name'] ?? '')) !== '') {
            $parentId = (string) ($newBauprojekt['parent_id'] ?? '');
            if ($parentId === '') {
                throw new \InvalidArgumentException('parent_id für neues Bauprojekt erforderlich');
            }
            $parent = $this->entityManager->getRepository(Group::class)->find($parentId);
            if ($parent === null || $parent->getDepartmentId() !== $department->getId()) {
                throw new \InvalidArgumentException('Parent-Ressort nicht gefunden');
            }
            if (!$this->access->canCreateChildGroup($user, $department, $parent)) {
                throw new \RuntimeException('Keine Berechtigung, Bauprojekt anzulegen');
            }

            $created = $this->groupService->createGroup($department, $user, [
                'name' => trim((string) $newBauprojekt['name']),
                'parent_id' => $parentId,
                'kind' => Group::GROSSANLASS_KIND_TEILBEREICH,
            ]);

            $group = $this->entityManager->getRepository(Group::class)->find($created['id']);
            if ($group === null) {
                throw new \RuntimeException('Bauprojekt konnte nicht erstellt werden');
            }

            return $group;
        }

        $groupId = (string) ($data['group_id'] ?? '');
        if ($groupId === '') {
            throw new \InvalidArgumentException('group_id oder new_bauprojekt erforderlich');
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ressort/Bauprojekt nicht gefunden');
        }

        return $group;
    }

    private function findWishInRound(Department $department, string $roundId, string $wishId): ActivityGrossanlassWishLine
    {
        $round = $this->roundService->findRoundForDepartment($department, $roundId);
        $line = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($wishId);
        if ($line === null || $line->getRoundId() !== $round->getId()) {
            throw new \InvalidArgumentException('Wunsch nicht gefunden');
        }

        return $line;
    }

    private function assertCanEditWish(Department $department, User $user, ActivityGrossanlassWishLine $line): void
    {
        if ($line->getRound()->getStatus() !== ActivityGrossanlassRound::STATUS_OPEN) {
            throw new \InvalidArgumentException('Wünsche nur in offenen Runden bearbeitbar');
        }
        if ($line->getCreatedByUserId() !== $user->getId()) {
            throw new \RuntimeException('Nur der Autor darf diesen Wunsch bearbeiten');
        }
        if (!$this->canWriteWishForGroup($department, $user, $line->getGroup())) {
            throw new \RuntimeException('Keine Berechtigung');
        }
    }

    private function canWriteWishForGroup(Department $department, User $user, Group $group): bool
    {
        if ($this->access->canManagePlanung($user, $department)) {
            return true;
        }

        return $this->access->userIsMemberInRessortBranch($user, $department->getId(), $group);
    }

    /**
     * @return list<string>|null null = alle (MW/DC)
     */
    private function resolveVisibleGroupIds(Department $department, User $user): ?array
    {
        if ($this->access->canManagePlanung($user, $department)) {
            return null;
        }

        $groups = $this->entityManager->getRepository(Group::class)
            ->findBy(['departmentId' => $department->getId()]);
        $visible = [];
        foreach ($groups as $group) {
            if ($this->access->userIsMemberInRessortBranch($user, $department->getId(), $group)) {
                $branch = $this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]);
                foreach ($branch as $id) {
                    $visible[$id] = true;
                }
            }
        }

        return array_keys($visible);
    }

    private function parseDateTime(mixed $value, string $field): \DateTime
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException($field . ' ist erforderlich');
        }
        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges Datum für ' . $field);
        }
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ActivityGrossanlassWishLine $line): array
    {
        return [
            'id' => $line->getId(),
            'round_id' => $line->getRoundId(),
            'group_id' => $line->getGroupId(),
            'group_name' => $line->getGroup()->getName(),
            'wish_kind' => $line->getWishKind(),
            'label' => $line->getLabel(),
            'quantity' => $line->getQuantity(),
            'location' => $line->getLocation(),
            'valid_from' => $line->getValidFrom()->format(\DateTimeInterface::ATOM),
            'valid_to' => $line->getValidTo()->format(\DateTimeInterface::ATOM),
            'timeframe_notes' => $line->getTimeframeNotes(),
            'notes' => $line->getNotes(),
            'status' => $line->getStatus(),
            'created_by_user_id' => $line->getCreatedByUserId(),
            'created_at' => $line->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $line->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
