<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementFinance;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponse;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GrossanlassProcurementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassProcurementQuoteStorageService $quoteStorage,
        private GrossanlassProcurementQuotePdfTextService $quotePdfText,
    ) {}

    /**
     * @return array{
     *     pool: list<array<string, mixed>>,
     *     lines: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     suggestions: list<array<string, mixed>>
     * }
     */
    public function getBedarfOverview(Department $department, User $user): array
    {
        $this->assertCanManageProcurement($department, $user);

        $pool = $this->listPoolWishes($department);

        return [
            'pool' => $pool,
            'lines' => $this->listLines($department),
            'categories' => $this->listCategoryArrays($department),
            'suggestions' => $this->enrichSuggestions($pool),
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
            ->andWhere('r.formPurpose = :purpose')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('purpose', ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH)
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
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('c.parent', 'cp')
            ->addSelect('cp')
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
        $line->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PROCUREMENT_LINE,
            ActivityGrossanlassProcurementLine::class,
        ));
        $line->setDepartment($department);
        $line->setCreatedByUser($user);
        $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_BEDARF);

        $this->applyWishAggregation($line, $wishes, $data);
        $this->applyCategory($line, $department, $data);

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
        GrossanlassProcurementQuantityFreeze::assertMergeAllowed($line->getStatus(), $line->getQuantityAsked());

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
        $this->applyCategory($line, $department, $data);
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
        if (isset($data['quantity']) && GrossanlassProcurementQuantityFreeze::isFrozen($line->getQuantityAsked())) {
            throw new \InvalidArgumentException('Angefragte Menge ist eingefroren');
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
        $this->applyCategory($line, $department, $data);

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
        GrossanlassProcurementQuantityFreeze::assertMergeAllowed($line->getStatus(), $line->getQuantityAsked());
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

    public function freezeAskedFromInquiry(Department $department, DepartmentGrossanlassInquiry $inquiry): void
    {
        if ($inquiry->getDepartmentId() !== $department->getId()) {
            return;
        }
        if ($inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_VORSCHLAG) {
            return;
        }
        $categoryIds = $inquiry->getCategoryIds();
        if ($categoryIds === []) {
            return;
        }
        $idSet = array_flip($categoryIds);
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine || $line->getQuantityAsked() !== null) {
                continue;
            }
            $catId = $line->getCategoryId();
            $parentId = $line->getCategory()?->getParentId();
            if ($catId !== null && isset($idSet[$catId])) {
                $line->setQuantityAsked($line->getQuantity());
                $line->touchUpdatedAt();
                continue;
            }
            if ($parentId !== null && isset($idSet[$parentId])) {
                $line->setQuantityAsked($line->getQuantity());
                $line->touchUpdatedAt();
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCategories(Department $department, User $user): array
    {
        $this->assertCanManageProcurement($department, $user);

        return $this->listCategoryArrays($department);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createCategory(Department $department, User $user, array $data): array
    {
        $this->assertCanManageProcurement($department, $user);

        $name = $this->requireCategoryName($data['name'] ?? null);
        $parent = $this->resolveCategoryParent($department, $data['parent_id'] ?? null, null);
        $this->assertUniqueCategoryName($department, $name, $parent?->getId(), null);

        $category = new ActivityGrossanlassProcurementCategory();
        $category->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PROCUREMENT_CATEGORY,
            ActivityGrossanlassProcurementCategory::class,
        ));
        $category->setDepartment($department);
        $category->setParent($parent);
        $category->setName($name);
        $category->setSortOrder((int) ($data['sort_order'] ?? 0));

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $this->categoryToArray($category);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateCategory(Department $department, User $user, string $categoryId, array $data): array
    {
        $this->assertCanManageProcurement($department, $user);

        $category = $this->findCategoryInDepartment($department, $categoryId);

        if (isset($data['name'])) {
            $name = $this->requireCategoryName($data['name']);
            $parentId = array_key_exists('parent_id', $data)
                ? ($data['parent_id'] ? (string) $data['parent_id'] : null)
                : $category->getParentId();
            $this->assertUniqueCategoryName($department, $name, $parentId, $category->getId());
            $category->setName($name);
        }
        if (array_key_exists('parent_id', $data)) {
            $parent = $this->resolveCategoryParent($department, $data['parent_id'], $category);
            $this->assertUniqueCategoryName(
                $department,
                $category->getName(),
                $parent?->getId(),
                $category->getId(),
            );
            $category->setParent($parent);
        }
        if (isset($data['sort_order'])) {
            $category->setSortOrder((int) $data['sort_order']);
        }

        $category->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->categoryToArray($category);
    }

    public function deleteCategory(Department $department, User $user, string $categoryId): void
    {
        $this->assertCanManageProcurement($department, $user);

        $category = $this->findCategoryInDepartment($department, $categoryId);
        $children = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->findBy(['parentId' => $category->getId()]);
        foreach ($children as $child) {
            if ($child instanceof ActivityGrossanlassProcurementCategory) {
                $this->clearCategoryOnLines($child);
                $this->entityManager->remove($child);
            }
        }
        $this->clearCategoryOnLines($category);
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('c.parent', 'cp')
            ->addSelect('cp')
            ->where('p.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->getQuery()
            ->getResult();

        $sollChf = 0.0;
        $istChf = 0.0;
        $openQuotesCount = 0;
        $orderedNotReceivedCount = 0;
        $byStatus = [];
        /** @var array<string, array{group_id: string, group_name: string, soll_chf: float, ist_chf: float, line_count: int}> $byGroup */
        $byGroup = [];
        /** @var array<string, array{category_id: string|null, category_name: string|null, parent_id: string|null, parent_name: string|null, rahmen_chf: float|null, soll_chf: float, ist_chf: float, line_count: int}> $byCategory */
        $byCategory = [];

        foreach ($this->loadCategories($department) as $category) {
            $parent = $category->getParent();
            $byCategory[$category->getId()] = [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'parent_id' => $parent?->getId(),
                'parent_name' => $parent?->getName(),
                'rahmen_chf' => $this->decimalToFloat($category->getRahmenChf()),
                'soll_chf' => 0.0,
                'ist_chf' => 0.0,
                'line_count' => 0,
            ];
        }

        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $status = $line->getStatus();
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            $groupId = $line->getGroupId();
            if (!isset($byGroup[$groupId])) {
                $byGroup[$groupId] = [
                    'group_id' => $groupId,
                    'group_name' => $line->getGroup()->getName(),
                    'soll_chf' => 0.0,
                    'ist_chf' => 0.0,
                    'line_count' => 0,
                ];
            }
            $byGroup[$groupId]['line_count']++;

            $categoryKey = $line->getCategoryId() ?? '_uncategorized';
            if (!isset($byCategory[$categoryKey])) {
                $category = $line->getCategory();
                $parent = $category?->getParent();
                $byCategory[$categoryKey] = [
                    'category_id' => $category?->getId(),
                    'category_name' => $category?->getName(),
                    'parent_id' => $parent?->getId(),
                    'parent_name' => $parent?->getName(),
                    'rahmen_chf' => $this->decimalToFloat($category?->getRahmenChf()),
                    'soll_chf' => 0.0,
                    'ist_chf' => 0.0,
                    'line_count' => 0,
                ];
            }
            $byCategory[$categoryKey]['line_count']++;

            $selectedQuote = $this->findSelectedQuote($line);
            if ($selectedQuote !== null) {
                $amount = (float) $selectedQuote->getAmountChf();
                $sollChf += $amount;
                $byGroup[$groupId]['soll_chf'] += $amount;
                $byCategory[$categoryKey]['soll_chf'] += $amount;
            }

            $order = $this->findOrderForLine($line);
            if ($order !== null) {
                $cost = (float) $order->getCostChf();
                $istChf += $cost;
                $byGroup[$groupId]['ist_chf'] += $cost;
                $byCategory[$categoryKey]['ist_chf'] += $cost;
            }

            $quotes = $this->loadQuotesForLine($line);
            $hasSelected = $selectedQuote !== null;
            if ($quotes !== [] && !$hasSelected && in_array($status, [
                ActivityGrossanlassProcurementLine::STATUS_BEDARF,
                ActivityGrossanlassProcurementLine::STATUS_OFFERTE,
            ], true)) {
                ++$openQuotesCount;
            }

            if ($order !== null && !in_array($status, [
                ActivityGrossanlassProcurementLine::STATUS_ERHALTEN,
            ], true)) {
                ++$orderedNotReceivedCount;
            }
        }

        $finance = $this->entityManager->find(ActivityGrossanlassProcurementFinance::class, $department->getId());
        $rahmenChf = $this->decimalToFloat($finance?->getRahmenChf());

        return [
            'totals' => [
                'line_count' => count($lines),
                'rahmen_chf' => $rahmenChf,
                'soll_chf' => round($sollChf, 2),
                'ist_chf' => round($istChf, 2),
                'delta_chf' => round($sollChf - $istChf, 2),
                'rahmen_minus_ist_chf' => $rahmenChf !== null ? round($rahmenChf - $istChf, 2) : null,
                'rahmen_minus_soll_chf' => $rahmenChf !== null ? round($rahmenChf - $sollChf, 2) : null,
                'open_quotes_count' => $openQuotesCount,
                'ordered_not_received_count' => $orderedNotReceivedCount,
            ],
            'by_status' => $byStatus,
            'by_group' => array_values($byGroup),
            'by_category' => array_values($byCategory),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function saveFinance(Department $department, User $user, array $data): array
    {
        $this->assertCanManageProcurement($department, $user);

        $finance = $this->entityManager->find(ActivityGrossanlassProcurementFinance::class, $department->getId());
        if (!$finance instanceof ActivityGrossanlassProcurementFinance) {
            $finance = new ActivityGrossanlassProcurementFinance();
            $finance->setDepartment($department);
            $this->entityManager->persist($finance);
        }

        if (array_key_exists('rahmen_chf', $data)) {
            $finance->setRahmenChf($this->parseOptionalAmountChf($data['rahmen_chf']));
        }
        $finance->touchUpdatedAt();

        $categoryRows = $data['categories'] ?? null;
        if (is_array($categoryRows)) {
            foreach ($categoryRows as $row) {
                if (!is_array($row) || !isset($row['category_id'])) {
                    continue;
                }
                $category = $this->findCategoryInDepartment($department, (string) $row['category_id']);
                if (array_key_exists('rahmen_chf', $row)) {
                    $category->setRahmenChf($this->parseOptionalAmountChf($row['rahmen_chf']));
                    $category->touchUpdatedAt();
                }
            }
        }

        $this->entityManager->flush();

        return $this->getOverview($department, $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAllLines(Department $department, User $user, ?string $statusFilter = null): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $qb = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('c.parent', 'cp')
            ->addSelect('cp')
            ->where('p.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('p.updatedAt', 'DESC');

        if ($statusFilter !== null && $statusFilter !== '') {
            $qb->andWhere('p.status = :status')->setParameter('status', $statusFilter);
        }

        $lines = $qb->getQuery()->getResult();
        $result = [];
        foreach ($lines as $line) {
            if ($line instanceof ActivityGrossanlassProcurementLine) {
                $result[] = $this->lineToArray($line);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createQuote(Department $department, User $user, string $lineId, array $data): array
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        $this->assertLineAllowsQuoteEdit($line);

        $supplierAddress = null;
        if (!empty($data['supplier_address_id'])) {
            $supplierAddress = $this->findSupplierAddressInDepartment($department, (string) $data['supplier_address_id']);
        }

        $supplier = trim((string) ($data['supplier'] ?? ''));
        if ($supplier === '' && $supplierAddress !== null) {
            $supplier = $this->supplierDisplayName($supplierAddress);
        }
        if ($supplier === '') {
            throw new \InvalidArgumentException('Lieferant ist erforderlich');
        }
        $amount = $this->parseAmountChf($data['amount_chf'] ?? null);

        $quote = new ActivityGrossanlassProcurementQuote();
        $quote->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PROCUREMENT_QUOTE,
            ActivityGrossanlassProcurementQuote::class,
        ));
        $quote->setProcurementLine($line);
        $quote->setSupplier($supplier);
        if ($supplierAddress !== null) {
            $quote->setSupplierAddress($supplierAddress);
        }
        $quote->setAmountChf(number_format($amount, 2, '.', ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $quote->setNotes($notes === '' ? null : $notes);

        $this->entityManager->persist($quote);

        if ($line->getStatus() === ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_OFFERTE);
        }
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->quoteToArray($quote, $department);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateQuote(Department $department, User $user, string $lineId, string $quoteId, array $data): array
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        $quote = $this->findQuoteInLine($line, $quoteId);
        $this->assertLineAllowsQuoteEdit($line);

        if (array_key_exists('supplier_address_id', $data)) {
            $addressId = trim((string) ($data['supplier_address_id'] ?? ''));
            if ($addressId === '') {
                $quote->setSupplierAddress(null);
            } else {
                $quote->setSupplierAddress($this->findSupplierAddressInDepartment($department, $addressId));
            }
        }

        if (isset($data['supplier'])) {
            $supplier = trim((string) $data['supplier']);
            if ($supplier === '' && $quote->getSupplierAddress() !== null) {
                $supplier = $this->supplierDisplayName($quote->getSupplierAddress());
            }
            if ($supplier === '') {
                throw new \InvalidArgumentException('Lieferant ist erforderlich');
            }
            $quote->setSupplier($supplier);
        } elseif ($quote->getSupplierAddress() !== null && trim($quote->getSupplier()) === '') {
            $quote->setSupplier($this->supplierDisplayName($quote->getSupplierAddress()));
        }
        if (array_key_exists('amount_chf', $data)) {
            $amount = $this->parseAmountChf($data['amount_chf']);
            $quote->setAmountChf(number_format($amount, 2, '.', ''));
        }
        if (array_key_exists('notes', $data)) {
            $notes = trim((string) ($data['notes'] ?? ''));
            $quote->setNotes($notes === '' ? null : $notes);
        }

        $quote->touchUpdatedAt();
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->quoteToArray($quote, $department);
    }

    public function deleteQuote(Department $department, User $user, string $lineId, string $quoteId): void
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        $quote = $this->findQuoteInLine($line, $quoteId);
        $this->assertLineAllowsQuoteEdit($line);

        if ($quote->isSelected()) {
            throw new \InvalidArgumentException('Gewählte Offerte kann nicht gelöscht werden');
        }

        $pdfFilename = $quote->getPdfFilename();
        $departmentId = $department->getId() ?? '';

        $this->entityManager->remove($quote);
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        if ($pdfFilename !== null && $pdfFilename !== '') {
            $this->quoteStorage->deleteFile($departmentId, $quoteId, $pdfFilename);
        }

        $this->syncLineStatusAfterQuoteChange($line);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadQuotePdf(
        Department $department,
        User $user,
        string $lineId,
        string $quoteId,
        UploadedFile $file,
    ): array {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        $quote = $this->findQuoteInLine($line, $quoteId);
        $this->assertLineAllowsQuoteEdit($line);

        $departmentId = $department->getId() ?? '';
        $previousFilename = $quote->getPdfFilename();

        $stored = $this->quoteStorage->store($department, $quote, $user, $file);
        $quote->setPdfFilename($stored['filename']);
        $quote->touchUpdatedAt();
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        if ($previousFilename !== null && $previousFilename !== '' && $previousFilename !== $stored['filename']) {
            $this->quoteStorage->deleteFile($departmentId, $quoteId, $previousFilename);
        }

        return $this->quoteToArray($quote, $department);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractContactFromQuotePdf(Department $department, User $user, UploadedFile $file): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        return $this->quotePdfText->extractFromUploadedFile($file);
    }

    public function resolveQuotePdfPath(Department $department, User $user, string $quoteId, string $filename): string
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $quote = $this->entityManager->getRepository(ActivityGrossanlassProcurementQuote::class)->find($quoteId);
        if (!$quote instanceof ActivityGrossanlassProcurementQuote) {
            throw new \InvalidArgumentException('Offerte nicht gefunden');
        }

        $line = $quote->getProcurementLine();
        if ($line->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Offerte nicht gefunden');
        }

        if ($quote->getPdfFilename() !== $filename) {
            throw new \InvalidArgumentException('PDF nicht gefunden');
        }

        return $this->quoteStorage->resolveFilePath($department->getId() ?? '', $quoteId, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    public function selectQuote(Department $department, User $user, string $lineId, string $quoteId): array
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        $quote = $this->findQuoteInLine($line, $quoteId);
        $this->assertLineAllowsQuoteEdit($line);

        foreach ($this->loadQuotesForLine($line) as $existing) {
            $existing->setSelected($existing->getId() === $quote->getId());
            $existing->touchUpdatedAt();
        }

        $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_BUDGETIERT);
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function upsertOrder(Department $department, User $user, string $lineId, array $data): array
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        if ($this->findSelectedQuote($line) === null) {
            throw new \InvalidArgumentException('Bitte zuerst eine Offerte wählen (Budget)');
        }
        if (!in_array($line->getStatus(), [
            ActivityGrossanlassProcurementLine::STATUS_BUDGETIERT,
            ActivityGrossanlassProcurementLine::STATUS_BESTELLT,
            ActivityGrossanlassProcurementLine::STATUS_TEILWEISE,
        ], true)) {
            throw new \InvalidArgumentException('Bestellung nur für budgetierte Positionen möglich');
        }

        $cost = $this->parseAmountChf($data['cost_chf'] ?? null);
        $order = $this->findOrderForLine($line);

        if ($order === null) {
            $order = new ActivityGrossanlassProcurementOrder();
            $order->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::PROCUREMENT_ORDER,
                ActivityGrossanlassProcurementOrder::class,
            ));
            $order->setProcurementLine($line);
            $this->entityManager->persist($order);
        }

        if (!empty($data['ordered_at'])) {
            $order->setOrderedAt(new \DateTime((string) $data['ordered_at']));
        }
        $order->setCostChf(number_format($cost, 2, '.', ''));
        $orderRef = trim((string) ($data['order_ref'] ?? ''));
        $order->setOrderRef($orderRef === '' ? null : $orderRef);
        $notes = trim((string) ($data['notes'] ?? ''));
        $order->setNotes($notes === '' ? null : $notes);
        $order->touchUpdatedAt();

        if ($line->getStatus() === ActivityGrossanlassProcurementLine::STATUS_BUDGETIERT) {
            $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_BESTELLT);
        }
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function recordReceived(Department $department, User $user, string $lineId, array $data): array
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        if ($this->findOrderForLine($line) === null) {
            throw new \InvalidArgumentException('Position muss zuerst bestellt sein');
        }
        if ($line->getStatus() === ActivityGrossanlassProcurementLine::STATUS_ERHALTEN) {
            throw new \InvalidArgumentException('Position ist bereits vollständig erhalten');
        }

        $links = $this->loadWishLinksForLine($line);
        if ($links === []) {
            throw new \InvalidArgumentException('Keine Grundeingaben verknüpft');
        }

        if (!empty($data['full'])) {
            foreach ($links as $link) {
                $link->setReceivedQuantity($link->getWishLine()->getQuantity());
            }
        } else {
            $allocations = is_array($data['allocations'] ?? null) ? $data['allocations'] : [];
            $byWishId = [];
            foreach ($links as $link) {
                $byWishId[$link->getWishLineId()] = $link;
            }
            foreach ($allocations as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $wishId = (string) ($row['wish_line_id'] ?? '');
                if ($wishId === '' || !isset($byWishId[$wishId])) {
                    throw new \InvalidArgumentException('Ungültige Grundeingabe in Verteilung');
                }
                $qty = (int) ($row['quantity'] ?? 0);
                if ($qty < 0) {
                    throw new \InvalidArgumentException('Menge darf nicht negativ sein');
                }
                $wishQty = $byWishId[$wishId]->getWishLine()->getQuantity();
                if ($qty > $wishQty) {
                    throw new \InvalidArgumentException('Verteilte Menge überschreitet Wunschmenge');
                }
                $byWishId[$wishId]->setReceivedQuantity($qty);
            }
        }

        $receivedSum = 0;
        foreach ($links as $link) {
            $receivedSum += $link->getReceivedQuantity();
        }

        if ($receivedSum >= $line->getQuantity()) {
            $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_ERHALTEN);
        } elseif ($receivedSum > 0) {
            $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_TEILWEISE);
        }

        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeWishFromLine(Department $department, User $user, string $lineId, string $wishLineId): array
    {
        $line = $this->requireLineForProcurement($department, $user, $lineId);
        GrossanlassProcurementQuantityFreeze::assertMergeAllowed($line->getStatus(), $line->getQuantityAsked());
        if ($line->getStatus() !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            throw new \InvalidArgumentException('Position kann nur im Status «Bedarf» aufgeteilt werden');
        }

        $links = $this->loadWishLinksForLine($line);
        if (count($links) <= 1) {
            throw new \InvalidArgumentException('Mindestens ein Wunsch muss in der Position bleiben — zum Entfernen Position löschen');
        }

        $target = null;
        foreach ($links as $link) {
            if ($link->getWishLineId() === $wishLineId) {
                $target = $link;
                break;
            }
        }
        if ($target === null) {
            throw new \InvalidArgumentException('Wunsch nicht in dieser Position');
        }

        $this->releaseWishFromProcurement($target->getWishLine(), $user);
        $this->entityManager->remove($target);

        $remaining = array_filter($links, static fn ($l) => $l !== $target);
        $wishes = array_map(static fn ($l) => $l->getWishLine(), $remaining);
        $this->applyWishAggregation($line, $wishes, []);
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->lineToArray($line);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     pool: list<array<string, mixed>>,
     *     lines: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     suggestions: list<array<string, mixed>>
     * }
     */
    public function updateBedarfWish(Department $department, User $user, string $wishLineId, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        $wish = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($wishLineId);
        if (!$wish instanceof ActivityGrossanlassWishLine) {
            throw new \InvalidArgumentException('Wunsch nicht gefunden');
        }
        if (!$this->wishBelongsToDepartment($wish, $department)) {
            throw new \InvalidArgumentException('Wunsch nicht gefunden');
        }

        $link = $this->findWishLinkInDepartment($department, $wishLineId);
        if ($link !== null) {
            $line = $link->getProcurementLine();
            if ($line->getStatus() !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
                throw new \InvalidArgumentException('Grundeingabe kann nur bei Position im Status «Bedarf» bearbeitet werden');
            }
        }

        if (isset($data['label'])) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new \InvalidArgumentException('Bezeichnung darf nicht leer sein');
            }
            $wish->setLabel($label);
        }
        if (isset($data['quantity'])) {
            $qty = (int) $data['quantity'];
            if ($qty < 1) {
                throw new \InvalidArgumentException('Anzahl muss mindestens 1 sein');
            }
            $wish->setQuantity($qty);
        }
        if (isset($data['location'])) {
            $wish->setLocation(trim((string) $data['location']));
        }
        if (array_key_exists('notes', $data)) {
            $notes = trim((string) ($data['notes'] ?? ''));
            $wish->setNotes($notes === '' ? null : $notes);
        }

        $wish->touchUpdatedAt();
        $response = $wish->getResponse();
        if ($response !== null) {
            $response->touchUpdatedAt($user);
        }

        $this->entityManager->flush();

        return $this->getBedarfOverview($department, $user);
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
            if ($wish->getRound()->getFormPurpose() !== ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH) {
                throw new \InvalidArgumentException('Nur Materialwünsche gehören in den Bedarf-Pool');
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
        if ($line->getQuantityAsked() === null) {
            $line->setQuantity($quantity);
        }
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

    private function findWishLinkInDepartment(Department $department, string $wishLineId): ?ActivityGrossanlassProcurementLineWish
    {
        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['wishLineId' => $wishLineId]);
        foreach ($links as $link) {
            if ($link instanceof ActivityGrossanlassProcurementLineWish
                && $link->getProcurementLine()->getDepartmentId() === $department->getId()) {
                return $link;
            }
        }

        return null;
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

    private function assertCanManageProcurement(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyCategory(
        ActivityGrossanlassProcurementLine $line,
        Department $department,
        array $data,
    ): void {
        if (!array_key_exists('category_id', $data)) {
            return;
        }
        $categoryId = $data['category_id'];
        if ($categoryId === null || $categoryId === '') {
            $line->setCategory(null);

            return;
        }
        $line->setCategory($this->findCategoryInDepartment($department, (string) $categoryId));
    }

    /**
     * @param list<array<string, mixed>> $pool
     *
     * @return list<array<string, mixed>>
     */
    private function enrichSuggestions(array $pool): array
    {
        $byId = [];
        foreach ($pool as $wish) {
            $id = (string) ($wish['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $wish;
            }
        }

        $groups = GrossanlassProcurementBundleSuggester::suggest($pool);
        foreach ($groups as &$group) {
            $wishes = [];
            foreach ($group['wish_ids'] as $wishId) {
                if (isset($byId[$wishId])) {
                    $wishes[] = $byId[$wishId];
                }
            }
            $group['wishes'] = $wishes;
        }
        unset($group);

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listCategoryArrays(Department $department): array
    {
        return array_map($this->categoryToArray(...), $this->loadCategories($department));
    }

    /**
     * @return list<ActivityGrossanlassProcurementCategory>
     */
    private function loadCategories(Department $department): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->where('c.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $parents = [];
        $childrenByParent = [];
        foreach ($rows as $row) {
            if (!$row instanceof ActivityGrossanlassProcurementCategory) {
                continue;
            }
            if ($row->getParentId() === null) {
                $parents[] = $row;
            } else {
                $childrenByParent[$row->getParentId()][] = $row;
            }
        }

        $ordered = [];
        foreach ($parents as $parent) {
            $ordered[] = $parent;
            foreach ($childrenByParent[$parent->getId()] ?? [] as $child) {
                $ordered[] = $child;
            }
            unset($childrenByParent[$parent->getId()]);
        }
        foreach ($childrenByParent as $orphans) {
            foreach ($orphans as $child) {
                $ordered[] = $child;
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryToArray(ActivityGrossanlassProcurementCategory $category): array
    {
        $parent = $category->getParent();

        return [
            'id' => $category->getId(),
            'department_id' => $category->getDepartmentId(),
            'parent_id' => $category->getParentId(),
            'parent_name' => $parent?->getName(),
            'name' => $category->getName(),
            'sort_order' => $category->getSortOrder(),
            'rahmen_chf' => $this->decimalToFloat($category->getRahmenChf()),
        ];
    }

    private function findCategoryInDepartment(
        Department $department,
        string $categoryId,
    ): ActivityGrossanlassProcurementCategory {
        $category = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)->find($categoryId);
        if (!$category instanceof ActivityGrossanlassProcurementCategory
            || $category->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Kategorie nicht gefunden');
        }

        return $category;
    }

    private function resolveCategoryParent(
        Department $department,
        mixed $parentId,
        ?ActivityGrossanlassProcurementCategory $category,
    ): ?ActivityGrossanlassProcurementCategory {
        $parentId = is_string($parentId) || is_int($parentId) ? trim((string) $parentId) : '';
        if ($parentId === '') {
            return null;
        }
        if ($category !== null && $parentId === $category->getId()) {
            throw new \InvalidArgumentException('Kategorie kann nicht sich selbst untergeordnet werden');
        }
        $parent = $this->findCategoryInDepartment($department, $parentId);
        if ($parent->getParentId() !== null) {
            throw new \InvalidArgumentException('Nur eine Unterebene erlaubt');
        }
        if ($category !== null) {
            $childCount = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
                ->count(['parentId' => $category->getId()]);
            if ($childCount > 0) {
                throw new \InvalidArgumentException('Kategorie mit Unterkategorien kann nicht verschoben werden');
            }
        }

        return $parent;
    }

    private function requireCategoryName(mixed $value): string
    {
        $name = trim((string) ($value ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        if (mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Name ist zu lang');
        }

        return $name;
    }

    private function assertUniqueCategoryName(
        Department $department,
        string $name,
        ?string $parentId,
        ?string $excludeId,
    ): void {
        $qb = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->createQueryBuilder('c')
            ->where('c.departmentId = :departmentId')
            ->andWhere('LOWER(c.name) = :name')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('name', mb_strtolower($name, 'UTF-8'));

        if ($parentId === null) {
            $qb->andWhere('c.parentId IS NULL');
        } else {
            $qb->andWhere('c.parentId = :parentId')->setParameter('parentId', $parentId);
        }
        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        $existing = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($existing instanceof ActivityGrossanlassProcurementCategory) {
            throw new \InvalidArgumentException('Kategorie existiert bereits');
        }
    }

    private function clearCategoryOnLines(ActivityGrossanlassProcurementCategory $category): void
    {
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(['categoryId' => $category->getId()]);
        foreach ($lines as $line) {
            if ($line instanceof ActivityGrossanlassProcurementLine) {
                $line->setCategory(null);
                $line->touchUpdatedAt();
            }
        }
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
            'last_stage' => $wish->getLastStage(),
            'label' => $wish->getLabel(),
            'quantity' => $wish->getQuantity(),
            'location' => $wish->getLocation(),
            'valid_from' => $wish->getValidFrom()->format(\DateTimeInterface::ATOM),
            'valid_to' => $wish->getValidTo()->format(\DateTimeInterface::ATOM),
            'notes' => $wish->getNotes(),
            'created_by_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'created_at' => $wish->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineToArray(ActivityGrossanlassProcurementLine $line): array
    {
        $links = $this->loadWishLinksForLine($line);
        $sourceWishes = [];
        $sourceQuantitySum = 0;
        $receivedQuantitySum = 0;
        foreach ($links as $link) {
            $sourceWishes[] = $this->wishLinkToSourceArray($link);
            $sourceQuantitySum += $link->getWishLine()->getQuantity();
            $receivedQuantitySum += $link->getReceivedQuantity();
        }

        $quotes = array_map(fn ($q) => $this->quoteToArray($q), $this->loadQuotesForLine($line));
        $selectedQuote = $this->findSelectedQuote($line);
        $order = $this->findOrderForLine($line);
        $category = $line->getCategory();
        $parent = $category?->getParent();

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
            'category_id' => $category?->getId(),
            'category_name' => $category?->getName(),
            'category_parent_id' => $parent?->getId(),
            'category_parent_name' => $parent?->getName(),
            'status' => $line->getStatus(),
            'quantity_asked' => $line->getQuantityAsked(),
            'quantity_current' => $sourceQuantitySum,
            'quantity_delta' => GrossanlassProcurementQuantityFreeze::delta($line->getQuantityAsked(), $sourceQuantitySum),
            'merge_frozen' => GrossanlassProcurementQuantityFreeze::isFrozen($line->getQuantityAsked()),
            'wish_line_ids' => array_map(static fn (array $w) => (string) $w['id'], $sourceWishes),
            'wish_count' => count($sourceWishes),
            'source_wishes' => $sourceWishes,
            'source_quantity_sum' => $sourceQuantitySum,
            'received_quantity_sum' => $receivedQuantitySum,
            'quotes' => $quotes,
            'selected_quote_id' => $selectedQuote?->getId(),
            'budget_chf' => $selectedQuote !== null ? (float) $selectedQuote->getAmountChf() : null,
            'order' => $order !== null ? $this->orderToArray($order) : null,
            'created_at' => $line->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $line->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wishLinkToSourceArray(ActivityGrossanlassProcurementLineWish $link): array
    {
        $base = $this->wishToPoolArray($link->getWishLine());
        $base['received_quantity'] = $link->getReceivedQuantity();

        return $base;
    }

    /**
     * @return list<ActivityGrossanlassProcurementLineWish>
     */
    private function loadWishLinksForLine(ActivityGrossanlassProcurementLine $line): array
    {
        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['procurementLineId' => $line->getId()]);

        return array_values(array_filter($links, static fn ($l) => $l instanceof ActivityGrossanlassProcurementLineWish));
    }

    /**
     * @return list<ActivityGrossanlassProcurementQuote>
     */
    private function loadQuotesForLine(ActivityGrossanlassProcurementLine $line): array
    {
        $quotes = $this->entityManager->getRepository(ActivityGrossanlassProcurementQuote::class)
            ->findBy(['procurementLineId' => $line->getId()], ['amountChf' => 'ASC', 'createdAt' => 'ASC']);

        return array_values(array_filter($quotes, static fn ($q) => $q instanceof ActivityGrossanlassProcurementQuote));
    }

    private function findSelectedQuote(ActivityGrossanlassProcurementLine $line): ?ActivityGrossanlassProcurementQuote
    {
        foreach ($this->loadQuotesForLine($line) as $quote) {
            if ($quote->isSelected()) {
                return $quote;
            }
        }

        return null;
    }

    private function findOrderForLine(ActivityGrossanlassProcurementLine $line): ?ActivityGrossanlassProcurementOrder
    {
        $order = $this->entityManager->getRepository(ActivityGrossanlassProcurementOrder::class)
            ->findOneBy(['procurementLineId' => $line->getId()]);

        return $order instanceof ActivityGrossanlassProcurementOrder ? $order : null;
    }

    private function findQuoteInLine(ActivityGrossanlassProcurementLine $line, string $quoteId): ActivityGrossanlassProcurementQuote
    {
        $quote = $this->entityManager->getRepository(ActivityGrossanlassProcurementQuote::class)->find($quoteId);
        if ($quote === null || $quote->getProcurementLineId() !== $line->getId()) {
            throw new \InvalidArgumentException('Offerte nicht gefunden');
        }

        return $quote;
    }

    private function requireLineForProcurement(
        Department $department,
        User $user,
        string $lineId,
    ): ActivityGrossanlassProcurementLine {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Beschaffung');
        }

        return $this->findLineInDepartment($department, $lineId);
    }

    private function assertLineAllowsQuoteEdit(ActivityGrossanlassProcurementLine $line): void
    {
        if (!in_array($line->getStatus(), [
            ActivityGrossanlassProcurementLine::STATUS_BEDARF,
            ActivityGrossanlassProcurementLine::STATUS_OFFERTE,
            ActivityGrossanlassProcurementLine::STATUS_BUDGETIERT,
        ], true)) {
            throw new \InvalidArgumentException('Offerten können in diesem Status nicht mehr bearbeitet werden');
        }
    }

    private function syncLineStatusAfterQuoteChange(ActivityGrossanlassProcurementLine $line): void
    {
        $quotes = $this->loadQuotesForLine($line);
        if ($quotes === []) {
            if ($line->getStatus() === ActivityGrossanlassProcurementLine::STATUS_OFFERTE) {
                $line->setStatus(ActivityGrossanlassProcurementLine::STATUS_BEDARF);
                $line->touchUpdatedAt();
            }
            $this->entityManager->flush();
        }
    }

    private function parseAmountChf(mixed $value): float
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException('Betrag ist erforderlich');
        }
        if (is_string($value)) {
            $value = str_replace(["'", '’', ' '], '', $value);
            $value = str_replace(',', '.', $value);
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Ungültiger Betrag');
        }
        $amount = (float) $value;
        if ($amount < 0) {
            throw new \InvalidArgumentException('Betrag darf nicht negativ sein');
        }

        return $amount;
    }

    private function parseOptionalAmountChf(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format($this->parseAmountChf($value), 2, '.', '');
    }

    private function decimalToFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteToArray(ActivityGrossanlassProcurementQuote $quote, ?Department $department = null): array
    {
        $departmentId = $department?->getId() ?? $quote->getProcurementLine()->getDepartmentId();
        $pdfFilename = $quote->getPdfFilename();
        $supplierAddress = $quote->getSupplierAddress();

        return [
            'id' => $quote->getId(),
            'procurement_line_id' => $quote->getProcurementLineId(),
            'supplier' => $quote->getSupplier(),
            'supplier_address_id' => $quote->getSupplierAddressId(),
            'supplier_address' => $supplierAddress !== null ? $this->supplierAddressSummary($supplierAddress) : null,
            'amount_chf' => (float) $quote->getAmountChf(),
            'notes' => $quote->getNotes(),
            'selected' => $quote->isSelected(),
            'pdf_filename' => $pdfFilename,
            'pdf_url' => ($pdfFilename !== null && $pdfFilename !== '' && $departmentId !== null)
                ? $this->quoteStorage->buildPdfUrl($departmentId, $quote->getId(), $pdfFilename)
                : null,
            'created_at' => $quote->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $quote->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function findSupplierAddressInDepartment(Department $department, string $addressId): Address
    {
        $address = $this->entityManager->getRepository(Address::class)->find($addressId);
        if (!$address instanceof Address) {
            throw new \InvalidArgumentException('Kontakt nicht gefunden');
        }
        if ($address->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Kontakt gehört nicht zu dieser Abteilung');
        }
        if ($address->getDeletedAt() !== null) {
            throw new \InvalidArgumentException('Kontakt ist gelöscht');
        }

        return $address;
    }

    private function supplierDisplayName(Address $address): string
    {
        $company = trim((string) ($address->getCompany() ?? ''));
        if ($company !== '') {
            return $company;
        }
        $name = trim((string) ($address->getName() ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim($address->getStreetLine() ?: $address->getCity() ?: 'Lieferant');
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierAddressSummary(Address $address): array
    {
        return [
            'id' => $address->getId(),
            'type' => $address->getType(),
            'name' => $address->getName(),
            'company' => $address->getCompany(),
            'email' => $address->getEmail(),
            'phone' => $address->getPhone(),
            'city_line' => $address->getCityLine(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderToArray(ActivityGrossanlassProcurementOrder $order): array
    {
        return [
            'id' => $order->getId(),
            'procurement_line_id' => $order->getProcurementLineId(),
            'ordered_at' => $order->getOrderedAt()->format(\DateTimeInterface::ATOM),
            'cost_chf' => (float) $order->getCostChf(),
            'order_ref' => $order->getOrderRef(),
            'notes' => $order->getNotes(),
            'created_at' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
