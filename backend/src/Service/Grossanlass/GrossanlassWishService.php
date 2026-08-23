<?php

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassRoundForm;
use App\Entity\ActivityGrossanlassRoundFormField;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponse;
use App\Entity\ActivityGrossanlassWishResponseValue;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use App\Service\GroupHierarchyService;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassWishService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassGroupService $groupService,
        private GrossanlassPlanningRoundService $roundService,
        private GrossanlassRoundFormService $formService,
        private GroupHierarchyService $hierarchy,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listWishes(Department $department, User $user, string $roundId, ?string $groupIdFilter = null): array
    {
        return $this->listWishesPaginated($department, $user, $roundId, [
            'group_id' => $groupIdFilter,
        ])['items'];
    }

    /**
     * @param array{group_id?: string|null, status?: string|null, q?: string|null, page?: int, limit?: int} $filters
     *
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   counts: array{requested: int, accepted: int}
     * }
     */
    public function listWishesPaginated(Department $department, User $user, string $roundId, array $filters = []): array
    {
        $round = $this->roundService->findRoundForDepartment($department, $roundId);
        $allowedGroupIds = $this->resolveVisibleGroupIds($department, $user);

        $groupIdFilter = isset($filters['group_id']) && $filters['group_id'] !== '' ? (string) $filters['group_id'] : null;
        $statusFilter = isset($filters['status']) && $filters['status'] !== '' ? (string) $filters['status'] : null;
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(10, (int) ($filters['limit'] ?? 50)));

        $countQb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->select('w.status, COUNT(w.id) AS cnt')
            ->where('w.roundId = :roundId')
            ->setParameter('roundId', $round->getId())
            ->groupBy('w.status');

        if ($allowedGroupIds !== null) {
            if ($allowedGroupIds === []) {
                return [
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'counts' => ['requested' => 0, 'accepted' => 0],
                ];
            }
            $countQb->andWhere('w.groupId IN (:groupIds)')->setParameter('groupIds', $allowedGroupIds);
        }

        if ($groupIdFilter !== null) {
            $countQb->andWhere('w.groupId = :groupId')->setParameter('groupId', $groupIdFilter);
        }

        $counts = ['requested' => 0, 'accepted' => 0];
        foreach ($countQb->getQuery()->getArrayResult() as $row) {
            $status = (string) ($row['status'] ?? '');
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($status === ActivityGrossanlassWishLine::STATUS_REQUESTED) {
                $counts['requested'] = $cnt;
            } elseif ($status === ActivityGrossanlassWishLine::STATUS_ACCEPTED) {
                $counts['accepted'] = $cnt;
            }
        }

        $qb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.group', 'g')
            ->innerJoin('w.createdByUser', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('g', 'u', 'p')
            ->where('w.roundId = :roundId')
            ->setParameter('roundId', $round->getId())
            ->orderBy('w.createdAt', 'DESC');

        if ($allowedGroupIds !== null) {
            $qb->andWhere('w.groupId IN (:groupIds)')->setParameter('groupIds', $allowedGroupIds);
        }
        if ($groupIdFilter !== null) {
            $qb->andWhere('w.groupId = :groupId')->setParameter('groupId', $groupIdFilter);
        }
        if ($statusFilter !== null) {
            $qb->andWhere('w.status = :status')->setParameter('status', $statusFilter);
        }
        if ($search !== '') {
            $qb->andWhere(
                'LOWER(w.label) LIKE :q OR LOWER(w.location) LIKE :q OR LOWER(g.name) LIKE :q OR LOWER(p.firstName) LIKE :q OR LOWER(p.lastName) LIKE :q OR LOWER(p.nickname) LIKE :q',
            )->setParameter('q', '%' . strtolower($search) . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(w.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $lines = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassWishLine) {
                continue;
            }
            $items[] = $this->toArray($line);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'counts' => $counts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function acceptWish(Department $department, User $user, string $roundId, string $wishId): array
    {
        if (!$this->access->canAcceptWishResponses($user, $department)) {
            throw new \RuntimeException('Nur Materialwart darf Eingaben annehmen');
        }

        $line = $this->findWishInRound($department, $roundId, $wishId);
        if ($line->getStatus() !== ActivityGrossanlassWishLine::STATUS_REQUESTED) {
            throw new \InvalidArgumentException('Eingabe wurde bereits bearbeitet');
        }

        $line->setStatus(ActivityGrossanlassWishLine::STATUS_ACCEPTED);
        $line->touchUpdatedAt();

        $response = $line->getResponse();
        if ($response !== null) {
            $response->setStatus(ActivityGrossanlassWishResponse::STATUS_ACCEPTED);
            $response->touchUpdatedAt($user);
        }

        $this->entityManager->flush();

        return $this->toArray($line);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWishesForUserRessort(Department $department, User $user): array
    {
        $allowedGroupIds = $this->access->resolveAssignedGroupBranchIds($user, $department->getId());
        if ($allowedGroupIds === []) {
            return [];
        }

        $qb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->innerJoin('w.group', 'g')
            ->innerJoin('w.createdByUser', 'u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('g', 'u', 'p')
            ->where('a.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('w.createdAt', 'DESC');

        $qb->andWhere('w.groupId IN (:groupIds)')->setParameter('groupIds', $allowedGroupIds);

        $lines = $qb->getQuery()->getResult();

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

        $refineId = trim((string) ($data['refine_wish_id'] ?? ''));
        if ($refineId !== '') {
            return $this->refineExistingWish($department, $user, $round, $refineId, $data);
        }

        $form = $this->formService->findOrCreateFormForRound($round);
        $parsed = $this->parsePayloadAgainstForm($department, $user, $data, $form);

        if (!$this->canWriteWishForGroup($department, $user, $parsed['group'])) {
            throw new \RuntimeException('Keine Berechtigung für dieses Ressort/Bauprojekt');
        }

        $response = new ActivityGrossanlassWishResponse();
        $response->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::WISH_RESPONSE, ActivityGrossanlassWishResponse::class));
        $response->setRound($round);
        $response->setForm($form);
        $response->setGroup($parsed['group']);
        $response->setCreatedByUser($user);
        $response->setStatus(ActivityGrossanlassWishResponse::STATUS_REQUESTED);

        $line = new ActivityGrossanlassWishLine();
        $line->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::WISH_LINE, ActivityGrossanlassWishLine::class));
        $line->setRound($round);
        $line->setGroup($parsed['group']);
        $line->setWishKind($parsed['wish_kind']);
        $line->setLabel($parsed['label']);
        $line->setQuantity($parsed['quantity']);
        $line->setLocation($parsed['location']);
        $line->setValidFrom($parsed['valid_from']);
        $line->setValidTo($parsed['valid_to']);
        $line->setTimeframeNotes($parsed['timeframe_notes']);
        $line->setNotes($parsed['notes']);
        $line->setCreatedByUser($user);
        $line->setStatus(ActivityGrossanlassWishLine::STATUS_REQUESTED);
        $line->setLastStage(
            GrossanlassMaterialStage::isFein($round->getMaterialStage())
                ? GrossanlassMaterialStage::FEIN
                : GrossanlassMaterialStage::GROB
        );
        $line->setResponse($response);

        $this->entityManager->persist($response);
        $this->entityManager->persist($line);
        $this->persistCustomValues($response, $parsed['custom_values']);

        $this->entityManager->flush();

        return $this->toArray($line, $parsed['custom_values']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRefineCandidates(Department $department, User $user, string $feinRoundId): array
    {
        $round = $this->roundService->findRoundForDepartment($department, $feinRoundId);
        if ($round->getFormPurpose() !== ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH
            || !GrossanlassMaterialStage::isFein($round->getMaterialStage())
        ) {
            return [];
        }

        $qb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->innerJoin('w.group', 'g')
            ->where('a.departmentId = :departmentId')
            ->andWhere('r.formPurpose = :purpose')
            ->andWhere('r.id != :feinRoundId')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('purpose', ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH)
            ->setParameter('feinRoundId', $round->getId())
            ->orderBy('w.createdAt', 'DESC');

        $visible = $this->resolveVisibleGroupIds($department, $user);
        if ($visible !== null) {
            if ($visible === []) {
                return [];
            }
            $qb->andWhere('w.groupId IN (:groupIds)')->setParameter('groupIds', $visible);
        }

        $lines = $qb->getQuery()->getResult();

        return array_map(fn (ActivityGrossanlassWishLine $w) => $this->toArray($w), $lines);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function refineExistingWish(
        Department $department,
        User $user,
        ActivityGrossanlassRound $feinRound,
        string $wishId,
        array $data,
    ): array {
        if ($feinRound->getFormPurpose() !== ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH
            || !GrossanlassMaterialStage::isFein($feinRound->getMaterialStage())
        ) {
            throw new \InvalidArgumentException('Verfeinern nur in einem offenen Fein-Materialformular');
        }

        $line = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($wishId);
        if ($line === null || $line->getRound()->getActivity()->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Wunsch nicht gefunden');
        }
        if ($line->getRound()->getFormPurpose() !== ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH) {
            throw new \InvalidArgumentException('Nur Materialwünsche lassen sich verfeinern');
        }
        if ($line->getRoundId() === $feinRound->getId()) {
            throw new \InvalidArgumentException('Neuer Fein-Wunsch braucht keine refine_wish_id');
        }

        $this->assertCanEditWish($department, $user, $line, false);

        $form = $this->formService->findOrCreateFormForRound($feinRound);
        $merged = $this->mergeWishDataForUpdate($line, $data);
        $parsed = $this->parsePayloadAgainstForm($department, $user, $merged, $form, $line->getGroup());

        if (!$this->canWriteWishForGroup($department, $user, $parsed['group'])) {
            throw new \RuntimeException('Keine Berechtigung');
        }

        $line->setGroup($parsed['group']);
        $line->setWishKind($parsed['wish_kind']);
        $line->setLabel($parsed['label']);
        $line->setQuantity($parsed['quantity']);
        $line->setLocation($parsed['location']);
        $line->setValidFrom($parsed['valid_from']);
        $line->setValidTo($parsed['valid_to']);
        $line->setTimeframeNotes($parsed['timeframe_notes']);
        $line->setNotes($parsed['notes']);
        $line->setLastStage(GrossanlassMaterialStage::FEIN);
        $line->touchUpdatedAt();

        $response = $line->getResponse();
        if ($response !== null) {
            $response->setGroup($parsed['group']);
            $response->touchUpdatedAt($user);
            $this->replaceCustomValues($response, $parsed['custom_values']);
        }

        $this->entityManager->flush();

        return $this->toArray($line, $parsed['custom_values']);
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

        $form = $this->formService->findOrCreateFormForRound($line->getRound());
        $merged = $this->mergeWishDataForUpdate($line, $data);
        $parsed = $this->parsePayloadAgainstForm($department, $user, $merged, $form, $line->getGroup());

        if (!$this->canWriteWishForGroup($department, $user, $parsed['group'])) {
            throw new \RuntimeException('Keine Berechtigung');
        }

        $line->setGroup($parsed['group']);
        $line->setWishKind($parsed['wish_kind']);
        $line->setLabel($parsed['label']);
        $line->setQuantity($parsed['quantity']);
        $line->setLocation($parsed['location']);
        $line->setValidFrom($parsed['valid_from']);
        $line->setValidTo($parsed['valid_to']);
        $line->setTimeframeNotes($parsed['timeframe_notes']);
        $line->setNotes($parsed['notes']);
        if (GrossanlassMaterialStage::isFein($line->getRound()->getMaterialStage())) {
            $line->setLastStage(GrossanlassMaterialStage::FEIN);
        }
        $line->touchUpdatedAt();

        $response = $line->getResponse();
        if ($response === null) {
            $response = new ActivityGrossanlassWishResponse();
            $response->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::WISH_RESPONSE, ActivityGrossanlassWishResponse::class));
            $response->setRound($line->getRound());
            $response->setForm($form);
            $response->setCreatedByUser($line->getCreatedByUser());
            $response->setStatus($line->getStatus());
            $line->setResponse($response);
            $this->entityManager->persist($response);
        }
        $response->setGroup($parsed['group']);
        $response->touchUpdatedAt($user);

        $this->replaceCustomValues($response, $parsed['custom_values']);
        $this->entityManager->flush();

        return $this->toArray($line, $parsed['custom_values']);
    }

    public function deleteWish(Department $department, User $user, string $roundId, string $wishId): void
    {
        $line = $this->findWishInRound($department, $roundId, $wishId);
        $this->assertCanEditWish($department, $user, $line);
        if ($this->wishHasFrozenProcurement($line)) {
            throw new \InvalidArgumentException('Wunsch ist an eine eingefrorene Beschaffungsposition gebunden');
        }

        $response = $line->getResponse();
        $line->setResponse(null);
        $this->entityManager->remove($line);
        if ($response !== null) {
            $this->entityManager->remove($response);
        }
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *   group: Group,
     *   wish_kind: string,
     *   label: string,
     *   quantity: int,
     *   location: string,
     *   valid_from: \DateTime,
     *   valid_to: \DateTime,
     *   timeframe_notes: ?string,
     *   notes: ?string,
     *   custom_values: array<string, mixed>
     * }
     */
    private function parsePayloadAgainstForm(
        Department $department,
        User $user,
        array $data,
        ActivityGrossanlassRoundForm $form,
        ?Group $defaultGroup = null,
    ): array {
        $fields = $this->formService->getEnabledInputFields($form);
        $customValuesInput = is_array($data['custom_values'] ?? null) ? $data['custom_values'] : [];

        $group = $defaultGroup;
        $wishKind = null;
        $label = null;
        $quantity = null;
        $location = null;
        $validFrom = null;
        $validTo = null;
        $timeframeNotes = null;
        $notes = null;
        /** @var array<string, mixed> $customValues */
        $customValues = [];

        $bauprojektEnabled = false;
        $ressortWahlEnabled = false;
        $bauprojektFieldRequired = false;
        $ressortFieldRequired = false;
        /** @var array<string, true> $enabledSystemKeys */
        $enabledSystemKeys = [];
        foreach ($fields as $field) {
            $key = $field->getSystemKey();
            if ($key === GrossanlassFormFieldCatalog::SYSTEM_BAUPROJEKT) {
                $bauprojektEnabled = true;
                $bauprojektFieldRequired = $field->isRequired();
            }
            if ($key === GrossanlassFormFieldCatalog::SYSTEM_RESSORT_WAHL) {
                $ressortWahlEnabled = true;
                $ressortFieldRequired = $field->isRequired();
            }
            if ($key !== null) {
                $enabledSystemKeys[$key] = true;
            }
        }

        $resolvedBauprojektGroup = null;
        $resolvedRessortGroup = null;

        foreach ($fields as $field) {
            $systemKey = $field->getSystemKey();
            if ($systemKey !== null) {
                match ($systemKey) {
                    GrossanlassFormFieldCatalog::SYSTEM_BAUPROJEKT => $resolvedBauprojektGroup = $this->tryResolveGroupForCreate(
                        $department,
                        $user,
                        $data,
                        $field,
                    ),
                    GrossanlassFormFieldCatalog::SYSTEM_RESSORT_WAHL => $resolvedRessortGroup = $this->tryResolveGroupForRessortWahl(
                        $department,
                        $user,
                        $data,
                        $field,
                    ),
                    GrossanlassFormFieldCatalog::SYSTEM_WISH_KIND => $wishKind = $this->parseWishKind($data, $field),
                    GrossanlassFormFieldCatalog::SYSTEM_LABEL => $label = $this->parseRequiredString($data, 'label', $field),
                    GrossanlassFormFieldCatalog::SYSTEM_QUANTITY => $quantity = $this->parseQuantity($data, $field),
                    GrossanlassFormFieldCatalog::SYSTEM_LOCATION => $location = $this->parseRequiredString($data, 'location', $field),
                    GrossanlassFormFieldCatalog::SYSTEM_PERIOD => [$validFrom, $validTo, $timeframeNotes] = $this->parsePeriod($data, $field),
                    GrossanlassFormFieldCatalog::SYSTEM_NOTES => $notes = $this->parseOptionalFieldString($data, 'notes', $field),
                    default => null,
                };
                continue;
            }

            $customType = $field->getCustomType();
            if ($customType === null) {
                continue;
            }

            $fieldId = $field->getId();
            $raw = array_key_exists($fieldId, $customValuesInput) ? $customValuesInput[$fieldId] : null;
            $customValues[$fieldId] = $this->parseCustomValue($field, $raw);
        }

        if ($resolvedBauprojektGroup !== null) {
            $group = $resolvedBauprojektGroup;
        } elseif ($resolvedRessortGroup !== null) {
            $group = $resolvedRessortGroup;
        }

        if (!$bauprojektEnabled && !$ressortWahlEnabled && $group === null && $defaultGroup !== null) {
            $group = $defaultGroup;
        }
        if ($bauprojektEnabled && $bauprojektFieldRequired && $resolvedBauprojektGroup === null && $resolvedRessortGroup === null) {
            throw new \InvalidArgumentException('Bauprojekt ist erforderlich');
        }
        if ($ressortWahlEnabled && $ressortFieldRequired && $resolvedRessortGroup === null && $resolvedBauprojektGroup === null) {
            throw new \InvalidArgumentException('Ressort ist erforderlich');
        }
        if (($bauprojektEnabled || $ressortWahlEnabled) && $group === null) {
            throw new \InvalidArgumentException('Bauprojekt/Ressort ist erforderlich');
        }
        if ($group === null) {
            throw new \InvalidArgumentException('Bauprojekt/Ressort ist erforderlich');
        }

        if (isset($enabledSystemKeys[GrossanlassFormFieldCatalog::SYSTEM_WISH_KIND]) && $wishKind === null) {
            throw new \InvalidArgumentException('Art ist erforderlich');
        }
        if ($wishKind === null) {
            $wishKind = ActivityGrossanlassWishLine::KIND_MATERIAL;
        }
        if (isset($enabledSystemKeys[GrossanlassFormFieldCatalog::SYSTEM_LABEL]) && $label === null) {
            throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
        }
        if ($label === null) {
            $label = '';
        }
        if (isset($enabledSystemKeys[GrossanlassFormFieldCatalog::SYSTEM_QUANTITY]) && $quantity === null) {
            throw new \InvalidArgumentException('Anzahl ist erforderlich');
        }
        if ($quantity === null) {
            $quantity = 1;
        }
        if (isset($enabledSystemKeys[GrossanlassFormFieldCatalog::SYSTEM_LOCATION]) && $location === null) {
            throw new \InvalidArgumentException('Ort ist erforderlich');
        }
        if ($location === null) {
            $location = '';
        }
        if (isset($enabledSystemKeys[GrossanlassFormFieldCatalog::SYSTEM_PERIOD])) {
            if ($validFrom === null || $validTo === null) {
                throw new \InvalidArgumentException('Zeitraum ist erforderlich');
            }
            if ($validTo < $validFrom) {
                throw new \InvalidArgumentException('Zeitraum Ende muss nach Start liegen');
            }
        } else {
            $this->applyCustomDateRangeToPeriod($fields, $customValues, $validFrom, $validTo);
        }
        if ($validFrom === null) {
            $validFrom = new \DateTime();
        }
        if ($validTo === null) {
            $validTo = clone $validFrom;
        }

        $this->syncLineFieldsFromCustomValues($fields, $customValues, $label, $quantity, $location);

        return [
            'group' => $group,
            'wish_kind' => $wishKind,
            'label' => $label,
            'quantity' => $quantity,
            'location' => $location,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'timeframe_notes' => $timeframeNotes,
            'notes' => $notes,
            'custom_values' => $customValues,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function mergeWishDataForUpdate(ActivityGrossanlassWishLine $line, array $data): array
    {
        return array_merge([
            'group_id' => $line->getGroupId(),
            'wish_kind' => $line->getWishKind(),
            'label' => $line->getLabel(),
            'quantity' => $line->getQuantity(),
            'location' => $line->getLocation(),
            'valid_from' => $line->getValidFrom()->format(\DateTimeInterface::ATOM),
            'valid_to' => $line->getValidTo()->format(\DateTimeInterface::ATOM),
            'timeframe_notes' => $line->getTimeframeNotes(),
            'notes' => $line->getNotes(),
            'custom_values' => $this->loadCustomValuesForLine($line),
        ], $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCustomValuesForLine(ActivityGrossanlassWishLine $line): array
    {
        $response = $line->getResponse();
        if ($response === null) {
            return [];
        }

        $values = $this->entityManager->getRepository(ActivityGrossanlassWishResponseValue::class)
            ->findBy(['responseId' => $response->getId()]);

        $out = [];
        foreach ($values as $value) {
            if (!$value instanceof ActivityGrossanlassWishResponseValue) {
                continue;
            }
            $fieldId = $value->getFieldId();
            if ($value->getValueJson() !== null) {
                $out[$fieldId] = $value->getValueJson();
            } elseif ($value->getValueNumber() !== null) {
                $out[$fieldId] = (float) $value->getValueNumber();
            } else {
                $out[$fieldId] = $value->getValueText();
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $customValues
     */
    private function persistCustomValues(ActivityGrossanlassWishResponse $response, array $customValues): void
    {
        foreach ($customValues as $fieldId => $raw) {
            if (!is_string($fieldId)) {
                continue;
            }
            $field = $this->entityManager->getRepository(ActivityGrossanlassRoundFormField::class)->find($fieldId);
            if (!$field instanceof ActivityGrossanlassRoundFormField) {
                continue;
            }
            $value = new ActivityGrossanlassWishResponseValue();
            $value->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::WISH_VALUE, ActivityGrossanlassWishResponseValue::class));
            $value->setResponse($response);
            $value->setField($field);
            $this->applyValueToEntity($value, $field, $raw);
            $this->entityManager->persist($value);
        }
    }

    /**
     * @param array<string, mixed> $customValues
     */
    private function replaceCustomValues(ActivityGrossanlassWishResponse $response, array $customValues): void
    {
        $existing = $this->entityManager->getRepository(ActivityGrossanlassWishResponseValue::class)
            ->findBy(['responseId' => $response->getId()]);
        foreach ($existing as $value) {
            $this->entityManager->remove($value);
        }
        $this->persistCustomValues($response, $customValues);
    }

    private function applyValueToEntity(
        ActivityGrossanlassWishResponseValue $value,
        ActivityGrossanlassRoundFormField $field,
        mixed $raw,
    ): void {
        $type = $field->getCustomType();
        if ($type === GrossanlassFormFieldCatalog::CUSTOM_NUMBER) {
            $value->setValueNumber($raw === null ? null : (string) (float) $raw);

            return;
        }
        if ($type === GrossanlassFormFieldCatalog::CUSTOM_DATE_RANGE) {
            $value->setValueJson(is_array($raw) ? $raw : null);

            return;
        }
        if ($type === GrossanlassFormFieldCatalog::CUSTOM_SELECT) {
            $options = $field->getOptionsJson() ?? [];
            $multiple = ($options['multiple'] ?? false) === true;
            if ($multiple) {
                $value->setValueJson(is_array($raw) ? array_values($raw) : null);
                $value->setValueText(null);

                return;
            }
            $value->setValueText($raw === null ? null : trim((string) $raw));
            $value->setValueJson(null);

            return;
        }
        $value->setValueText($raw === null ? null : trim((string) $raw));
    }

    private function parseCustomValue(ActivityGrossanlassRoundFormField $field, mixed $raw): mixed
    {
        $label = $field->getLabel();
        $required = $field->isRequired();
        $type = $field->getCustomType();

        if ($type === GrossanlassFormFieldCatalog::CUSTOM_NUMBER) {
            if ($raw === null || $raw === '') {
                if ($required) {
                    throw new \InvalidArgumentException($label . ' ist erforderlich');
                }

                return null;
            }
            if (!is_numeric($raw)) {
                throw new \InvalidArgumentException($label . ' muss eine Zahl sein');
            }

            return (float) $raw;
        }

        if ($type === GrossanlassFormFieldCatalog::CUSTOM_SELECT) {
            $options = $field->getOptionsJson() ?? [];
            $choices = is_array($options['choices'] ?? null) ? $options['choices'] : [];
            $multiple = ($options['multiple'] ?? false) === true;

            if ($multiple) {
                if ($raw === null || $raw === '' || (is_array($raw) && $raw === [])) {
                    if ($required) {
                        throw new \InvalidArgumentException($label . ' ist erforderlich');
                    }

                    return [];
                }
                if (!is_array($raw)) {
                    throw new \InvalidArgumentException($label . ': ungültige Auswahl');
                }
                $selected = [];
                foreach ($raw as $item) {
                    $str = trim((string) $item);
                    if ($str === '') {
                        continue;
                    }
                    if ($choices !== [] && !in_array($str, $choices, true)) {
                        throw new \InvalidArgumentException($label . ': ungültige Auswahl');
                    }
                    if (!in_array($str, $selected, true)) {
                        $selected[] = $str;
                    }
                }
                if ($selected === [] && $required) {
                    throw new \InvalidArgumentException($label . ' ist erforderlich');
                }

                return $selected;
            }

            $str = $raw === null ? '' : trim((string) $raw);
            if ($str === '') {
                if ($required) {
                    throw new \InvalidArgumentException($label . ' ist erforderlich');
                }

                return null;
            }
            if ($choices !== [] && !in_array($str, $choices, true)) {
                throw new \InvalidArgumentException($label . ': ungültige Auswahl');
            }

            return $str;
        }

        if ($type === GrossanlassFormFieldCatalog::CUSTOM_DATE_RANGE) {
            if (!is_array($raw)) {
                if ($required) {
                    throw new \InvalidArgumentException($label . ' ist erforderlich');
                }

                return null;
            }
            $from = $raw['from'] ?? $raw['valid_from'] ?? null;
            $to = $raw['to'] ?? $raw['valid_to'] ?? null;
            if ($from === null || $to === null) {
                if ($required) {
                    throw new \InvalidArgumentException($label . ' ist erforderlich');
                }

                return null;
            }
            $fromDt = $this->parseDateTime($from, $label . ' Start');
            $toDt = $this->parseDateTime($to, $label . ' Ende');
            if ($toDt < $fromDt) {
                throw new \InvalidArgumentException($label . ': Ende muss nach Start liegen');
            }

            return [
                'from' => $fromDt->format(\DateTimeInterface::ATOM),
                'to' => $toDt->format(\DateTimeInterface::ATOM),
            ];
        }

        $str = $raw === null ? '' : trim((string) $raw);
        if ($str === '' && $required) {
            throw new \InvalidArgumentException($label . ' ist erforderlich');
        }

        return $str === '' ? null : $str;
    }

    private function parseWishKind(array $data, ActivityGrossanlassRoundFormField $field): string
    {
        $wishKind = (string) ($data['wish_kind'] ?? '');
        if ($wishKind === '') {
            if ($field->isRequired()) {
                throw new \InvalidArgumentException('Art ist erforderlich');
            }

            return ActivityGrossanlassWishLine::KIND_MATERIAL;
        }
        if (!in_array($wishKind, [
            ActivityGrossanlassWishLine::KIND_MATERIAL,
            ActivityGrossanlassWishLine::KIND_FAHRZEUG,
            ActivityGrossanlassWishLine::KIND_BEIDES,
        ], true)) {
            throw new \InvalidArgumentException('wish_kind ungültig');
        }

        return $wishKind;
    }

    private function parseRequiredString(array $data, string $key, ActivityGrossanlassRoundFormField $field): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            if ($field->isRequired()) {
                throw new \InvalidArgumentException($field->getLabel() . ' ist erforderlich');
            }

            return null;
        }

        return $value;
    }

    private function parseOptionalFieldString(array $data, string $key, ActivityGrossanlassRoundFormField $field): ?string
    {
        if (!array_key_exists($key, $data)) {
            return $field->isRequired() ? null : null;
        }
        $value = $this->optionalString($data[$key]);
        if ($value === null && $field->isRequired()) {
            throw new \InvalidArgumentException($field->getLabel() . ' ist erforderlich');
        }

        return $value;
    }

    private function parseQuantity(array $data, ActivityGrossanlassRoundFormField $field): ?int
    {
        if (!array_key_exists('quantity', $data)) {
            if ($field->isRequired()) {
                throw new \InvalidArgumentException($field->getLabel() . ' ist erforderlich');
            }

            return null;
        }
        $quantity = (int) $data['quantity'];
        if ($quantity < 1) {
            if ($field->isRequired()) {
                throw new \InvalidArgumentException('Anzahl muss mindestens 1 sein');
            }

            return null;
        }

        return $quantity;
    }

    /**
     * @return array{0: ?\DateTime, 1: ?\DateTime, 2: ?string}
     */
    private function parsePeriod(array $data, ActivityGrossanlassRoundFormField $field): array
    {
        $fromRaw = $data['valid_from'] ?? null;
        $toRaw = $data['valid_to'] ?? null;
        if (($fromRaw === null || $fromRaw === '') && ($toRaw === null || $toRaw === '')) {
            if ($field->isRequired()) {
                throw new \InvalidArgumentException($field->getLabel() . ' ist erforderlich');
            }

            return [null, null, $this->optionalString($data['timeframe_notes'] ?? null)];
        }

        $validFrom = $this->parseDateTime($fromRaw, 'valid_from');
        $validTo = $this->parseDateTime($toRaw, 'valid_to');

        return [$validFrom, $validTo, $this->optionalString($data['timeframe_notes'] ?? null)];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tryResolveGroupForCreate(
        Department $department,
        User $user,
        array $data,
        ActivityGrossanlassRoundFormField $field,
    ): ?Group {
        try {
            return $this->resolveGroupForCreate($department, $user, $data, $field);
        } catch (\InvalidArgumentException $e) {
            if (!$field->isRequired()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tryResolveGroupForRessortWahl(
        Department $department,
        User $user,
        array $data,
        ActivityGrossanlassRoundFormField $field,
    ): ?Group {
        try {
            return $this->resolveGroupForRessortWahl($department, $user, $data, $field);
        } catch (\InvalidArgumentException $e) {
            if (!$field->isRequired()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveGroupForCreate(
        Department $department,
        User $user,
        array $data,
        ActivityGrossanlassRoundFormField $field,
    ): Group {
        $config = $field->getConfigJson() ?? [];
        $allowNew = (bool) ($config['allow_new_bauprojekt'] ?? true);
        $leaderScope = (bool) ($config['leader_scope'] ?? false);

        $newBauprojekt = $data['new_bauprojekt'] ?? null;
        if (is_array($newBauprojekt) && trim((string) ($newBauprojekt['name'] ?? '')) !== '') {
            if (!$allowNew) {
                throw new \InvalidArgumentException('Neue Bauprojekte sind für diese Runde nicht erlaubt');
            }
            $parentId = (string) ($newBauprojekt['parent_id'] ?? '');
            if ($parentId === '') {
                throw new \InvalidArgumentException('parent_id für neues Bauprojekt erforderlich');
            }
            $parent = $this->entityManager->getRepository(Group::class)->find($parentId);
            if ($parent === null || $parent->getDepartmentId() !== $department->getId()) {
                throw new \InvalidArgumentException('Parent-Ressort nicht gefunden');
            }
            // §4.2: Bauprojekt anlegen dürfen alle Ressort-Mitglieder; leader_scope gilt nur für die Auswahl bestehender Bauprojekte.
            if (!$this->access->canCreateChildGroup($user, $department, $parent, false)) {
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
            if ($field->isRequired()) {
                throw new \InvalidArgumentException('Bauprojekt/Ressort ist erforderlich');
            }
            throw new \InvalidArgumentException('group_id oder new_bauprojekt erforderlich');
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ressort/Bauprojekt nicht gefunden');
        }
        if (!$this->access->canSelectBauprojektForWish($user, $department, $group, $leaderScope)) {
            throw new \RuntimeException('Keine Berechtigung für dieses Bauprojekt');
        }
        if (!$this->groupIsBauprojekt($group)) {
            throw new \InvalidArgumentException('Bitte ein Bauprojekt wählen');
        }

        return $group;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveGroupForRessortWahl(
        Department $department,
        User $user,
        array $data,
        ActivityGrossanlassRoundFormField $field,
    ): Group {
        $config = $field->getConfigJson() ?? [];
        $leaderScope = (bool) ($config['leader_scope'] ?? false);

        $groupId = (string) ($data['ressort_group_id'] ?? '');
        if ($groupId === '') {
            if ($field->isRequired()) {
                throw new \InvalidArgumentException('Ressort ist erforderlich');
            }
            throw new \InvalidArgumentException('ressort_group_id erforderlich');
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ressort nicht gefunden');
        }
        if ($this->groupIsBauprojekt($group)) {
            throw new \InvalidArgumentException('Bitte ein Ressort wählen, kein Bauprojekt');
        }
        if (!$this->access->canSelectRessortForWish($user, $department, $group, $leaderScope)) {
            throw new \RuntimeException('Keine Berechtigung für dieses Ressort');
        }

        return $group;
    }

    private function groupIsBauprojekt(Group $group): bool
    {
        if ($group->getParentId() === null || $group->getParentId() === '') {
            return false;
        }
        $kind = $group->getGrossanlassKind();

        return $kind === null || $kind === '' || $kind === Group::GROSSANLASS_KIND_TEILBEREICH;
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

    private function wishHasFrozenProcurement(ActivityGrossanlassWishLine $line): bool
    {
        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['wishLineId' => $line->getId()]);
        foreach ($links as $link) {
            if ($link instanceof ActivityGrossanlassProcurementLineWish
                && $link->getProcurementLine()->getQuantityAsked() !== null
            ) {
                return true;
            }
        }

        return false;
    }

    private function assertCanEditWish(
        Department $department,
        User $user,
        ActivityGrossanlassWishLine $line,
        bool $requireOpenRound = true,
    ): void {
        if ($requireOpenRound && $line->getRound()->getStatus() !== ActivityGrossanlassRound::STATUS_OPEN) {
            throw new \InvalidArgumentException('Wünsche nur in offenen Runden bearbeitbar');
        }

        $isAuthor = $line->getCreatedByUserId() === $user->getId();
        $isMaterialwart = $this->access->canManageGrossanlassForm($user, $department);

        if (!$isAuthor && !$isMaterialwart) {
            throw new \RuntimeException('Nur der Autor oder Materialwart darf diesen Wunsch bearbeiten');
        }

        if (!$isMaterialwart && !$this->canWriteWishForGroup($department, $user, $line->getGroup())) {
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

    /**
     * @param list<ActivityGrossanlassRoundFormField> $fields
     * @param array<string, mixed>                    $customValues
     */
    private function syncLineFieldsFromCustomValues(
        array $fields,
        array $customValues,
        ?string &$label,
        ?int &$quantity,
        ?string &$location,
    ): void {
        foreach ($fields as $field) {
            $fieldId = $field->getId();
            $raw = $customValues[$fieldId] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $type = $field->getCustomType();
            if ($type === GrossanlassFormFieldCatalog::CUSTOM_TEXT && ($label === null || $label === '')) {
                $text = trim((string) $raw);
                if ($text !== '') {
                    $label = $text;
                }
            } elseif ($type === GrossanlassFormFieldCatalog::CUSTOM_NUMBER && ($quantity === null || $quantity === 1)) {
                if (is_numeric($raw)) {
                    $qty = (int) $raw;
                    if ($qty >= 1) {
                        $quantity = $qty;
                    }
                }
            }
        }

        if ($location === null || $location === '') {
            foreach ($fields as $field) {
                if ($field->getCustomType() !== GrossanlassFormFieldCatalog::CUSTOM_TEXT) {
                    continue;
                }
                $labelLower = mb_strtolower($field->getLabel());
                if (!str_contains($labelLower, 'ort') && !str_contains($labelLower, 'wo ')) {
                    continue;
                }
                $raw = $customValues[$field->getId()] ?? null;
                $text = trim((string) ($raw ?? ''));
                if ($text !== '') {
                    $location = $text;
                    break;
                }
            }
        }

        if ($label === null) {
            $label = '';
        }
        if ($quantity === null) {
            $quantity = 1;
        }
        if ($location === null) {
            $location = '';
        }
    }

    /**
     * @param list<ActivityGrossanlassRoundFormField> $fields
     * @param array<string, mixed> $customValues
     */
    private function applyCustomDateRangeToPeriod(
        array $fields,
        array $customValues,
        ?\DateTime &$validFrom,
        ?\DateTime &$validTo,
    ): void {
        if ($validFrom !== null && $validTo !== null) {
            return;
        }

        foreach ($fields as $field) {
            if ($field->getCustomType() !== GrossanlassFormFieldCatalog::CUSTOM_DATE_RANGE) {
                continue;
            }
            $fieldId = $field->getId();
            $range = $customValues[$fieldId] ?? null;
            if (!is_array($range)) {
                continue;
            }
            $fromRaw = $range['from'] ?? null;
            $toRaw = $range['to'] ?? null;
            if ($fromRaw === null || $toRaw === null || $fromRaw === '' || $toRaw === '') {
                continue;
            }
            $from = $this->parseDateTime($fromRaw, $field->getLabel() . ' Start');
            $to = $this->parseDateTime($toRaw, $field->getLabel() . ' Ende');
            if ($to < $from) {
                throw new \InvalidArgumentException($field->getLabel() . ': Ende muss nach Start liegen');
            }
            $validFrom = $from;
            $validTo = $to;

            return;
        }
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
     * @param array<string, mixed>|null $customValues
     *
     * @return array<string, mixed>
     */
    private function toArray(ActivityGrossanlassWishLine $line, ?array $customValues = null): array
    {
        if ($customValues === null) {
            $customValues = $this->loadCustomValuesForLine($line);
        }

        $profile = $line->getCreatedByUser()->getProfile();

        return [
            'id' => $line->getId(),
            'round_id' => $line->getRoundId(),
            'response_id' => $line->getResponseId(),
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
            'last_stage' => $line->getLastStage(),
            'created_by_user_id' => $line->getCreatedByUserId(),
            'created_by_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'created_at' => $line->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $line->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'custom_values' => $customValues,
        ];
    }
}
