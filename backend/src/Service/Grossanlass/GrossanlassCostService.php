<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementFinance;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassBudget;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassCost;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassCostService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
    ) {}

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user, array $filters = []): array
    {
        $this->assertCanRead($department, $user);
        $costs = $this->loadCosts($department, $user, $filters);

        return array_map(fn (DepartmentGrossanlassCost $row) => $this->serialize($row), $costs);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertCanManage($department, $user);
        $row = new DepartmentGrossanlassCost();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::COST,
            DepartmentGrossanlassCost::class,
        ));
        $row->setDepartment($department);
        $this->apply($row, $department, $data, true);
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $id, array $data): array
    {
        $this->assertCanManage($department, $user);
        $row = $this->findInDepartment($department, $id);
        $this->apply($row, $department, $data, false);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    public function delete(Department $department, User $user, string $id): void
    {
        $this->assertCanManage($department, $user);
        $row = $this->findInDepartment($department, $id);
        $this->entityManager->remove($row);
        $this->entityManager->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBudgets(Department $department, User $user): array
    {
        $this->assertCanRead($department, $user);
        $this->bindCentralPotToLogisticsGroup($department);

        $rows = $this->loadBudgets($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            $branchIds = $this->access->resolveAssignedGroupBranchIds($user, $department->getId());
            $rows = array_values(array_filter(
                $rows,
                static fn (DepartmentGrossanlassBudget $row) => $row->getPayerGroupId() !== null
                    && in_array($row->getPayerGroupId(), $branchIds, true),
            ));
        }

        return array_map(fn (DepartmentGrossanlassBudget $row) => $this->serializeBudget($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function upsertBudget(Department $department, User $user, array $data): array
    {
        $this->assertCanManage($department, $user);
        $payerGroup = $this->resolvePayerGroup($department, $data['payer_group_id'] ?? null);
        $rahmen = $this->parseOptionalAmountChf($data['rahmen_chf'] ?? null);
        $row = $this->findBudget($department, $payerGroup?->getId());
        $isAnlassPot = $this->isAnlassPotPayer($department, $payerGroup?->getId());
        if ($rahmen === null) {
            if ($row instanceof DepartmentGrossanlassBudget) {
                $this->entityManager->remove($row);
                $this->entityManager->flush();
            }
            if ($isAnlassPot) {
                $this->syncFinanceSpiegel($department, null);
            }

            return ['payer_group_id' => $payerGroup?->getId(), 'rahmen_chf' => null];
        }

        if (!$row instanceof DepartmentGrossanlassBudget) {
            $row = new DepartmentGrossanlassBudget();
            $row->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::BUDGET,
                DepartmentGrossanlassBudget::class,
            ));
            $row->setDepartment($department);
            $this->entityManager->persist($row);
        }
        $row->setPayerGroup($payerGroup);
        $row->setRahmenChf($rahmen);
        $row->touchUpdatedAt();
        if ($isAnlassPot) {
            $this->syncFinanceSpiegel($department, $rahmen);
        }
        $this->entityManager->flush();

        return $this->serializeBudget($row);
    }

    public function ensureMainForLine(ActivityGrossanlassProcurementLine $line, array $data = []): DepartmentGrossanlassCost
    {
        $existing = $this->findMainForLine($line);
        if ($existing instanceof DepartmentGrossanlassCost) {
            if ($data !== []) {
                $this->apply($existing, $line->getDepartment(), $data, false);
            }

            return $existing;
        }

        $row = new DepartmentGrossanlassCost();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::COST,
            DepartmentGrossanlassCost::class,
        ));
        $row->setDepartment($line->getDepartment());
        $row->setProcurementLine($line);
        $row->setLabel($line->getLabel());
        $row->setRequestingGroup($line->getGroup());
        $row->setPayerGroup($line->getGroup());
        $row->setCategory($line->getCategory());
        $kind = (string) ($data['cost_kind'] ?? DepartmentGrossanlassCost::KIND_LOAN);
        if (!in_array($kind, DepartmentGrossanlassCost::KINDS, true) || $kind === DepartmentGrossanlassCost::KIND_ANCILLARY) {
            $kind = DepartmentGrossanlassCost::KIND_LOAN;
        }
        $row->setCostKind($kind);
        $this->normalizeKindFields($row);
        $this->apply($row, $line->getDepartment(), $data, false);
        $this->entityManager->persist($row);

        return $row;
    }

    public function syncFromSelectedQuote(ActivityGrossanlassProcurementLine $line, ActivityGrossanlassProcurementQuote $quote): void
    {
        $cost = $this->ensureMainForLine($line);
        $cost->setSollChf(number_format((float) $quote->getAmountChf(), 2, '.', ''));
        if (in_array($cost->getStatus(), [DepartmentGrossanlassCost::STATUS_PLANNED], true)) {
            $cost->setStatus(DepartmentGrossanlassCost::STATUS_COMMITTED);
        }
        $cost->touchUpdatedAt();
    }

    public function syncFromOrder(ActivityGrossanlassProcurementLine $line, ActivityGrossanlassProcurementOrder $order): void
    {
        $cost = $this->ensureMainForLine($line);
        if (in_array($cost->getCostKind(), [DepartmentGrossanlassCost::KIND_LOAN, DepartmentGrossanlassCost::KIND_RENTAL], true)) {
            $cost->setCostKind(DepartmentGrossanlassCost::KIND_PURCHASE);
            $cost->setAssetTreatment(DepartmentGrossanlassCost::ASSET_EXPENSE);
        }
        $cost->setCashOutChf(number_format((float) $order->getCostChf(), 2, '.', ''));
        if ($cost->getSollChf() === null) {
            $cost->setSollChf($cost->getCashOutChf());
        }
        $cost->setStatus(DepartmentGrossanlassCost::STATUS_PAID);
        $this->normalizeKindFields($cost);
        $cost->touchUpdatedAt();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function syncFromCommitment(DepartmentGrossanlassCommitment $commitment, array $data = []): DepartmentGrossanlassCost
    {
        $existing = $this->findMainForCommitment($commitment);
        $origin = $commitment->getOrigin();
        $kind = GrossanlassCostCalculator::kindFromOrigin($origin, isset($data['cost_kind']) ? (string) $data['cost_kind'] : null);
        if ($existing instanceof DepartmentGrossanlassCost) {
            $data['cost_kind'] = $kind;
            $this->apply($existing, $commitment->getDepartment(), $data, false);
            $existing->setCommitment($commitment);
            if ($existing->getLabel() === '') {
                $existing->setLabel($commitment->getName());
            }
            $existing->touchUpdatedAt();

            return $existing;
        }

        $row = new DepartmentGrossanlassCost();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::COST,
            DepartmentGrossanlassCost::class,
        ));
        $row->setDepartment($commitment->getDepartment());
        $row->setCommitment($commitment);
        $row->setLabel($commitment->getName());
        $row->setCostKind($kind);
        $this->normalizeKindFields($row);
        $this->apply($row, $commitment->getDepartment(), $data, false);
        if ($row->getPayerGroup() === null && $row->getRequestingGroup() !== null) {
            $row->setPayerGroup($row->getRequestingGroup());
        }
        $this->entityManager->persist($row);

        return $row;
    }

    public function recordGuestSaleProceeds(DepartmentGrossanlassCommitment $commitment, mixed $amount): void
    {
        $parsed = $this->parseOptionalAmountChf($amount);
        if ($parsed === null) {
            return;
        }

        $cost = $this->findMainForCommitment($commitment);
        if (!$cost instanceof DepartmentGrossanlassCost) {
            $cost = $this->syncFromCommitment($commitment, []);
        }

        if ($cost->getCostKind() === DepartmentGrossanlassCost::KIND_PURCHASE) {
            $cost->setCostKind(DepartmentGrossanlassCost::KIND_BUY_RESALE);
            $this->normalizeKindFields($cost);
        }

        $current = $this->decimalToFloat($cost->getProceedsActualChf()) ?? 0.0;
        $cost->setProceedsActualChf(number_format($current + (float) $parsed, 2, '.', ''));

        $expected = $this->decimalToFloat($cost->getProceedsExpectedChf());
        $actual = $this->decimalToFloat($cost->getProceedsActualChf()) ?? 0.0;
        if ($expected !== null && $actual >= $expected) {
            $cost->setStatus(DepartmentGrossanlassCost::STATUS_SOLD);
        } elseif ($cost->getStatus() !== DepartmentGrossanlassCost::STATUS_SOLD) {
            $cost->setStatus(DepartmentGrossanlassCost::STATUS_FOR_SALE);
        }
        $cost->touchUpdatedAt();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLedgerOverview(Department $department, User $user): array
    {
        $this->assertCanRead($department, $user);
        $this->bindCentralPotToLogisticsGroup($department);
        $this->backfillMissingLineCosts($department);

        $manage = $this->access->canManagePlanung($user, $department);
        $costs = $this->loadCosts($department, $user, []);
        $budgets = $this->loadBudgets($department);

        $cash = 0.0;
        $netto = 0.0;
        $soll = 0.0;
        $byKind = [];
        foreach (DepartmentGrossanlassCost::KINDS as $kind) {
            $byKind[$kind] = [
                'cost_kind' => $kind,
                'cash_chf' => 0.0,
                'netto_chf' => 0.0,
                'soll_chf' => 0.0,
                'line_count' => 0,
            ];
        }
        /** @var array<string, array{payer_group_id: string|null, payer_name: string, rahmen_chf: float|null, cash_chf: float, netto_chf: float, soll_chf: float, line_count: int}> $byPayer */
        $byPayer = [];
        /** @var array<string, array{group_id: string, group_name: string, soll_chf: float, ist_chf: float, cash_chf: float, netto_chf: float, line_count: int}> $byRequester */
        $byRequester = [];
        /** @var array<string, array{category_id: string|null, category_name: string|null, parent_id: string|null, parent_name: string|null, rahmen_chf: float|null, soll_chf: float, ist_chf: float, cash_chf: float, netto_chf: float, line_count: int}> $byCategory */
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
                'cash_chf' => 0.0,
                'netto_chf' => 0.0,
                'line_count' => 0,
            ];
        }

        $logistics = $this->logisticsGroup($department);
        $logisticsId = $logistics?->getId();
        $potCash = 0.0;
        $potNetto = 0.0;
        $potSoll = 0.0;

        foreach ($budgets as $budget) {
            $payerId = $this->effectivePayerGroupId($department, $budget->getPayerGroupId());
            $key = $payerId ?? '_central';
            $name = $budget->getPayerGroup()?->getName() ?? '';
            if ($payerId !== null && $name === '') {
                $name = $logistics?->getName() ?? '';
            }
            if (!isset($byPayer[$key])) {
                $byPayer[$key] = [
                    'payer_group_id' => $payerId,
                    'payer_name' => $name,
                    'rahmen_chf' => $this->decimalToFloat($budget->getRahmenChf()),
                    'cash_chf' => 0.0,
                    'netto_chf' => 0.0,
                    'soll_chf' => 0.0,
                    'line_count' => 0,
                ];
            } else {
                $existingRahmen = $byPayer[$key]['rahmen_chf'];
                $nextRahmen = $this->decimalToFloat($budget->getRahmenChf());
                if ($existingRahmen === null && $nextRahmen !== null) {
                    $byPayer[$key]['rahmen_chf'] = $nextRahmen;
                }
                if ($byPayer[$key]['payer_name'] === '' && $name !== '') {
                    $byPayer[$key]['payer_name'] = $name;
                }
            }
        }

        $serialized = [];
        foreach ($costs as $cost) {
            $amounts = GrossanlassCostCalculator::fromCost($cost);
            $cash += $amounts['cash_chf'];
            $netto += $amounts['netto_chf'];
            $soll += $amounts['soll_chf'];
            $kind = $cost->getCostKind();
            if (!isset($byKind[$kind])) {
                $byKind[$kind] = [
                    'cost_kind' => $kind,
                    'cash_chf' => 0.0,
                    'netto_chf' => 0.0,
                    'soll_chf' => 0.0,
                    'line_count' => 0,
                ];
            }
            $byKind[$kind]['cash_chf'] += $amounts['cash_chf'];
            $byKind[$kind]['netto_chf'] += $amounts['netto_chf'];
            $byKind[$kind]['soll_chf'] += $amounts['soll_chf'];
            $byKind[$kind]['line_count']++;

            $payerId = $this->effectivePayerGroupId($department, $cost->getPayerGroupId());
            $payerKey = $payerId ?? '_central';
            if (!isset($byPayer[$payerKey])) {
                $byPayer[$payerKey] = [
                    'payer_group_id' => $payerId,
                    'payer_name' => $cost->getPayerGroup()?->getName() ?? $logistics?->getName() ?? '',
                    'rahmen_chf' => null,
                    'cash_chf' => 0.0,
                    'netto_chf' => 0.0,
                    'soll_chf' => 0.0,
                    'line_count' => 0,
                ];
            }
            $byPayer[$payerKey]['cash_chf'] += $amounts['cash_chf'];
            $byPayer[$payerKey]['netto_chf'] += $amounts['netto_chf'];
            $byPayer[$payerKey]['soll_chf'] += $amounts['soll_chf'];
            $byPayer[$payerKey]['line_count']++;
            if ($this->isAnlassPotPayer($department, $payerId)) {
                $potCash += $amounts['cash_chf'];
                $potNetto += $amounts['netto_chf'];
                $potSoll += $amounts['soll_chf'];
            }

            $reqId = $cost->getRequestingGroupId();
            if ($reqId !== null) {
                if (!isset($byRequester[$reqId])) {
                    $byRequester[$reqId] = [
                        'group_id' => $reqId,
                        'group_name' => $cost->getRequestingGroup()?->getName() ?? '',
                        'soll_chf' => 0.0,
                        'ist_chf' => 0.0,
                        'cash_chf' => 0.0,
                        'netto_chf' => 0.0,
                        'line_count' => 0,
                    ];
                }
                $byRequester[$reqId]['soll_chf'] += $amounts['soll_chf'];
                $byRequester[$reqId]['ist_chf'] += $amounts['netto_chf'];
                $byRequester[$reqId]['cash_chf'] += $amounts['cash_chf'];
                $byRequester[$reqId]['netto_chf'] += $amounts['netto_chf'];
                $byRequester[$reqId]['line_count']++;
            }

            $catKey = $cost->getCategoryId() ?? '_uncategorized';
            if (!isset($byCategory[$catKey = $catKey])) {
                $category = $cost->getCategory();
                $parent = $category?->getParent();
                $byCategory[$catKey] = [
                    'category_id' => $category?->getId(),
                    'category_name' => $category?->getName(),
                    'parent_id' => $parent?->getId(),
                    'parent_name' => $parent?->getName(),
                    'rahmen_chf' => $this->decimalToFloat($category?->getRahmenChf()),
                    'soll_chf' => 0.0,
                    'ist_chf' => 0.0,
                    'cash_chf' => 0.0,
                    'netto_chf' => 0.0,
                    'line_count' => 0,
                ];
            }
            $byCategory[$catKey]['soll_chf'] += $amounts['soll_chf'];
            $byCategory[$catKey]['ist_chf'] += $amounts['netto_chf'];
            $byCategory[$catKey]['cash_chf'] += $amounts['cash_chf'];
            $byCategory[$catKey]['netto_chf'] += $amounts['netto_chf'];
            $byCategory[$catKey]['line_count']++;

            $serialized[] = $this->serialize($cost);
        }

        $centralRahmen = $this->decimalToFloat($this->findBudget($department, $logisticsId)?->getRahmenChf());
        foreach ($byKind as $kind => $row) {
            $byKind[$kind]['cash_chf'] = round($row['cash_chf'], 2);
            $byKind[$kind]['netto_chf'] = round($row['netto_chf'], 2);
            $byKind[$kind]['soll_chf'] = round($row['soll_chf'], 2);
        }
        foreach ($byPayer as $key => $row) {
            $byPayer[$key]['cash_chf'] = round($row['cash_chf'], 2);
            $byPayer[$key]['netto_chf'] = round($row['netto_chf'], 2);
            $byPayer[$key]['soll_chf'] = round($row['soll_chf'], 2);
            if ($row['payer_name'] === '') {
                $byPayer[$key]['payer_name'] = '';
            }
        }
        foreach ($byRequester as $key => $row) {
            $byRequester[$key]['soll_chf'] = round($row['soll_chf'], 2);
            $byRequester[$key]['ist_chf'] = round($row['ist_chf'], 2);
            $byRequester[$key]['cash_chf'] = round($row['cash_chf'], 2);
            $byRequester[$key]['netto_chf'] = round($row['netto_chf'], 2);
        }
        foreach ($byCategory as $key => $row) {
            $byCategory[$key]['soll_chf'] = round($row['soll_chf'], 2);
            $byCategory[$key]['ist_chf'] = round($row['ist_chf'], 2);
            $byCategory[$key]['cash_chf'] = round($row['cash_chf'], 2);
            $byCategory[$key]['netto_chf'] = round($row['netto_chf'], 2);
        }

        usort($byPayer, static function (array $a, array $b) use ($logisticsId): int {
            $aPot = $logisticsId !== null ? $a['payer_group_id'] === $logisticsId : $a['payer_group_id'] === null;
            $bPot = $logisticsId !== null ? $b['payer_group_id'] === $logisticsId : $b['payer_group_id'] === null;
            if ($aPot !== $bPot) {
                return $aPot ? -1 : 1;
            }

            return strcasecmp($a['payer_name'], $b['payer_name']);
        });

        return [
            'can_manage' => $manage,
            'logistics_group_id' => $logisticsId,
            'logistics_group_name' => $logistics?->getName(),
            'totals' => [
                'line_count' => count($serialized),
                'rahmen_chf' => $centralRahmen,
                'soll_chf' => round($soll, 2),
                'ist_chf' => round($netto, 2),
                'cash_chf' => round($cash, 2),
                'netto_chf' => round($netto, 2),
                'delta_chf' => round($soll - $netto, 2),
                'rahmen_minus_ist_chf' => $centralRahmen !== null ? round($centralRahmen - $potNetto, 2) : null,
                'rahmen_minus_cash_chf' => $centralRahmen !== null ? round($centralRahmen - $potCash, 2) : null,
                'rahmen_minus_soll_chf' => $centralRahmen !== null ? round($centralRahmen - $potSoll, 2) : null,
            ],
            'by_kind' => array_values($byKind),
            'by_payer' => array_values($byPayer),
            'by_requester' => array_values($byRequester),
            'by_group' => array_values($byRequester),
            'by_category' => array_values($byCategory),
            'costs' => $serialized,
            'budgets' => array_map(fn (DepartmentGrossanlassBudget $row) => $this->serializeBudget($row), $budgets),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(DepartmentGrossanlassCost $row, Department $department, array $data, bool $creating): void
    {
        if ($creating || array_key_exists('label', $data)) {
            $label = trim((string) ($data['label'] ?? $row->getLabel()));
            if ($label === '') {
                throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
            }
            $row->setLabel($label);
        }
        if ($creating || array_key_exists('cost_kind', $data)) {
            $kind = (string) ($data['cost_kind'] ?? $row->getCostKind());
            if (!in_array($kind, DepartmentGrossanlassCost::KINDS, true)) {
                throw new \InvalidArgumentException('Ungültige Kostenart');
            }
            if ($kind !== DepartmentGrossanlassCost::KIND_ANCILLARY && $creating === false) {
                $this->assertSingleMainKind($row, $kind);
            }
            $row->setCostKind($kind);
        }
        if ($creating || array_key_exists('asset_treatment', $data) || $row->getCostKind() === DepartmentGrossanlassCost::KIND_PURCHASE) {
            if ($row->getCostKind() === DepartmentGrossanlassCost::KIND_PURCHASE) {
                $treatment = (string) ($data['asset_treatment'] ?? $row->getAssetTreatment() ?? DepartmentGrossanlassCost::ASSET_EXPENSE);
                if (!in_array($treatment, DepartmentGrossanlassCost::ASSET_TREATMENTS, true)) {
                    throw new \InvalidArgumentException('Ungültige Behandlung des Einkaufs');
                }
                $row->setAssetTreatment($treatment);
            } else {
                $row->setAssetTreatment(null);
            }
        }
        if ($creating || array_key_exists('status', $data)) {
            $status = (string) ($data['status'] ?? $row->getStatus());
            if (!in_array($status, DepartmentGrossanlassCost::STATUSES, true)) {
                throw new \InvalidArgumentException('Ungültiger Kostenstatus');
            }
            $row->setStatus($status);
        }
        if (array_key_exists('procurement_line_id', $data)) {
            $lineId = $data['procurement_line_id'];
            if ($lineId === null || $lineId === '') {
                $row->setProcurementLine(null);
            } else {
                $row->setProcurementLine($this->findLineInDepartment($department, (string) $lineId));
            }
        }
        if (array_key_exists('commitment_id', $data)) {
            $commitmentId = $data['commitment_id'];
            if ($commitmentId === null || $commitmentId === '') {
                $row->setCommitment(null);
            } else {
                $row->setCommitment($this->findCommitmentInDepartment($department, (string) $commitmentId));
            }
        }
        if ($creating || array_key_exists('requesting_group_id', $data)) {
            $groupId = $data['requesting_group_id'] ?? $row->getRequestingGroupId();
            $row->setRequestingGroup($groupId ? $this->findGroupInDepartment($department, (string) $groupId) : null);
        }
        if ($creating || array_key_exists('payer_group_id', $data)) {
            if (!array_key_exists('payer_group_id', $data) && $creating && $row->getRequestingGroup() !== null) {
                $row->setPayerGroup($row->getRequestingGroup());
            } else {
                $payerId = $data['payer_group_id'] ?? null;
                $row->setPayerGroup($this->resolvePayerGroup($department, $payerId));
            }
        } elseif ($creating && $row->getPayerGroup() === null && $row->getRequestingGroup() !== null) {
            $row->setPayerGroup($row->getRequestingGroup());
        }
        if (array_key_exists('category_id', $data)) {
            $categoryId = $data['category_id'];
            $row->setCategory(($categoryId !== null && $categoryId !== '') ? $this->findCategoryInDepartment($department, (string) $categoryId) : null);
        }
        if (array_key_exists('partner_address_id', $data)) {
            $addressId = $data['partner_address_id'];
            $row->setPartnerAddress(($addressId !== null && $addressId !== '') ? $this->findAddressInDepartment($department, (string) $addressId) : null);
        }
        foreach ([
            'soll_chf' => 'setSollChf',
            'cash_out_chf' => 'setCashOutChf',
            'deposit_chf' => 'setDepositChf',
            'deposit_returned_chf' => 'setDepositReturnedChf',
            'proceeds_expected_chf' => 'setProceedsExpectedChf',
            'proceeds_actual_chf' => 'setProceedsActualChf',
        ] as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $row->{$setter}($this->parseOptionalAmountChf($data[$field]));
            }
        }
        if (array_key_exists('notes', $data)) {
            $notes = trim((string) ($data['notes'] ?? ''));
            $row->setNotes($notes === '' ? null : $notes);
        }
        $this->normalizeKindFields($row);
        if ($row->getProcurementLineId() === null && $row->getCommitmentId() === null && trim($row->getLabel()) === '') {
            throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
        }
        $row->touchUpdatedAt();
    }

    private function assertSingleMainKind(DepartmentGrossanlassCost $row, string $kind): void
    {
        if ($kind === DepartmentGrossanlassCost::KIND_ANCILLARY) {
            return;
        }
        $lineId = $row->getProcurementLineId();
        if ($lineId === null) {
            return;
        }
        $existing = $this->findMainForLineId($row->getDepartmentId(), $lineId);
        if ($existing instanceof DepartmentGrossanlassCost && $existing->getId() !== $row->getId() && $existing->isMainKind()) {
            throw new \InvalidArgumentException('Diese Position hat bereits eine Haupt-Kostenart');
        }
    }

    private function normalizeKindFields(DepartmentGrossanlassCost $row): void
    {
        $kind = $row->getCostKind();
        if ($kind !== DepartmentGrossanlassCost::KIND_PURCHASE) {
            $row->setAssetTreatment(null);
        } elseif ($row->getAssetTreatment() === null) {
            $row->setAssetTreatment(DepartmentGrossanlassCost::ASSET_EXPENSE);
        }
        if ($kind !== DepartmentGrossanlassCost::KIND_RENTAL) {
            $row->setDepositChf(null);
            $row->setDepositReturnedChf(null);
        }
        if ($kind !== DepartmentGrossanlassCost::KIND_BUY_RESALE) {
            $row->setProceedsExpectedChf(null);
            $row->setProceedsActualChf(null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassCost $row): array
    {
        $amounts = GrossanlassCostCalculator::fromCost($row);
        $category = $row->getCategory();

        return [
            'id' => $row->getId(),
            'department_id' => $row->getDepartmentId(),
            'procurement_line_id' => $row->getProcurementLineId(),
            'commitment_id' => $row->getCommitmentId(),
            'cost_kind' => $row->getCostKind(),
            'asset_treatment' => $row->getAssetTreatment(),
            'requesting_group_id' => $row->getRequestingGroupId(),
            'requesting_group_name' => $row->getRequestingGroup()?->getName(),
            'payer_group_id' => $row->getPayerGroupId(),
            'payer_group_name' => $row->getPayerGroup()?->getName(),
            'category_id' => $row->getCategoryId(),
            'category_name' => $category?->getName(),
            'label' => $row->getLabel(),
            'partner_address_id' => $row->getPartnerAddressId(),
            'soll_chf' => $this->decimalToFloat($row->getSollChf()),
            'cash_out_chf' => $this->decimalToFloat($row->getCashOutChf()),
            'deposit_chf' => $this->decimalToFloat($row->getDepositChf()),
            'deposit_returned_chf' => $this->decimalToFloat($row->getDepositReturnedChf()),
            'proceeds_expected_chf' => $this->decimalToFloat($row->getProceedsExpectedChf()),
            'proceeds_actual_chf' => $this->decimalToFloat($row->getProceedsActualChf()),
            'status' => $row->getStatus(),
            'notes' => $row->getNotes(),
            'cash_chf' => $amounts['cash_chf'],
            'netto_chf' => $amounts['netto_chf'],
            'created_at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $row->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBudget(DepartmentGrossanlassBudget $row): array
    {
        return [
            'id' => $row->getId(),
            'payer_group_id' => $row->getPayerGroupId(),
            'payer_name' => $row->getPayerGroup()?->getName(),
            'rahmen_chf' => $this->decimalToFloat($row->getRahmenChf()),
            'updated_at' => $row->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<DepartmentGrossanlassCost>
     */
    private function loadCosts(Department $department, User $user, array $filters): array
    {
        $qb = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.requestingGroup', 'rg')->addSelect('rg')
            ->leftJoin('c.payerGroup', 'pg')->addSelect('pg')
            ->leftJoin('c.category', 'cat')->addSelect('cat')
            ->where('c.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('c.createdAt', 'DESC');

        if (!$this->access->canManagePlanung($user, $department)) {
            $branchIds = $this->access->resolveAssignedGroupBranchIds($user, $department->getId());
            if ($branchIds === []) {
                return [];
            }
            $qb->andWhere('c.payerGroupId IN (:payerIds)')
                ->setParameter('payerIds', $branchIds);
        }
        if (!empty($filters['cost_kind'])) {
            $qb->andWhere('c.costKind = :kind')->setParameter('kind', (string) $filters['cost_kind']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')->setParameter('status', (string) $filters['status']);
        }
        if (array_key_exists('payer_group_id', $filters)) {
            $payer = $filters['payer_group_id'];
            if ($payer === null || $payer === '' || $payer === 'central') {
                $logisticsId = $this->logisticsGroup($department)?->getId();
                if ($logisticsId !== null) {
                    $qb->andWhere('c.payerGroupId = :payer')->setParameter('payer', $logisticsId);
                } else {
                    $qb->andWhere('c.payerGroupId IS NULL');
                }
            } else {
                $qb->andWhere('c.payerGroupId = :payer')->setParameter('payer', (string) $payer);
            }
        }
        if (!empty($filters['requesting_group_id'])) {
            $qb->andWhere('c.requestingGroupId = :req')->setParameter('req', (string) $filters['requesting_group_id']);
        }
        if (!empty($filters['category_id'])) {
            $qb->andWhere('c.categoryId = :cat')->setParameter('cat', (string) $filters['category_id']);
        }

        $rows = $qb->getQuery()->getResult();

        return array_values(array_filter($rows, static fn ($row) => $row instanceof DepartmentGrossanlassCost));
    }

    /**
     * @return list<DepartmentGrossanlassBudget>
     */
    private function loadBudgets(Department $department): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassBudget::class)
            ->findBy(['departmentId' => $department->getId()]);

        return array_values(array_filter($rows, static fn ($row) => $row instanceof DepartmentGrossanlassBudget));
    }

    private function findBudget(Department $department, ?string $payerGroupId): ?DepartmentGrossanlassBudget
    {
        $qb = $this->entityManager->getRepository(DepartmentGrossanlassBudget::class)
            ->createQueryBuilder('b')
            ->where('b.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId());
        if ($payerGroupId === null) {
            $qb->andWhere('b.payerGroupId IS NULL');
        } else {
            $qb->andWhere('b.payerGroupId = :payer')->setParameter('payer', $payerGroupId);
        }
        $row = $qb->getQuery()->getOneOrNullResult();

        return $row instanceof DepartmentGrossanlassBudget ? $row : null;
    }

    public function bindCentralPotToLogisticsGroup(Department $department, bool $flush = true): void
    {
        $logistics = $this->logisticsGroup($department);
        if ($logistics instanceof Group) {
            $nullBudget = $this->findBudget($department, null);
            $target = $this->findBudget($department, $logistics->getId());
            if ($nullBudget instanceof DepartmentGrossanlassBudget) {
                $rahmen = $nullBudget->getRahmenChf();
                if (!$target instanceof DepartmentGrossanlassBudget) {
                    $nullBudget->setPayerGroup($logistics);
                    $nullBudget->touchUpdatedAt();
                } else {
                    $target->setRahmenChf($rahmen);
                    $target->touchUpdatedAt();
                    $this->entityManager->remove($nullBudget);
                }
                $this->syncFinanceSpiegel($department, $rahmen);
            } elseif (!$target instanceof DepartmentGrossanlassBudget) {
                $this->copyLegacyRahmenToPayer($department, $logistics);
            }

            $costs = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)
                ->findBy(['departmentId' => $department->getId(), 'payerGroupId' => null]);
            foreach ($costs as $cost) {
                if ($cost instanceof DepartmentGrossanlassCost) {
                    $cost->setPayerGroup($logistics);
                    $cost->touchUpdatedAt();
                }
            }
        } else {
            $this->copyLegacyRahmenToPayer($department, null);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function copyLegacyRahmenToPayer(Department $department, ?Group $payer): void
    {
        if ($this->findBudget($department, $payer?->getId()) instanceof DepartmentGrossanlassBudget) {
            return;
        }
        $finance = $this->entityManager->find(ActivityGrossanlassProcurementFinance::class, $department->getId());
        $rahmen = $finance?->getRahmenChf();
        if ($rahmen === null || $rahmen === '') {
            return;
        }
        $row = new DepartmentGrossanlassBudget();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::BUDGET,
            DepartmentGrossanlassBudget::class,
        ));
        $row->setDepartment($department);
        $row->setPayerGroup($payer);
        $row->setRahmenChf($rahmen);
        $this->entityManager->persist($row);
    }

    private function logisticsGroup(Department $department): ?Group
    {
        $config = $department->getGrossanlassConfig();
        $group = $config?->getLogisticsGroup();
        if ($group instanceof Group) {
            return $group;
        }
        $id = $config?->getLogisticsGroupId();
        if ($id === null || $id === '') {
            return null;
        }
        $group = $this->entityManager->find(Group::class, $id);

        return ($group instanceof Group && $group->getDepartmentId() === $department->getId()) ? $group : null;
    }

    private function resolvePayerGroup(Department $department, mixed $payerGroupId): ?Group
    {
        if ($payerGroupId !== null && $payerGroupId !== '' && $payerGroupId !== 'central') {
            return $this->findGroupInDepartment($department, (string) $payerGroupId);
        }

        return $this->logisticsGroup($department);
    }

    private function effectivePayerGroupId(Department $department, ?string $payerGroupId): ?string
    {
        if ($payerGroupId !== null && $payerGroupId !== '') {
            return $payerGroupId;
        }

        return $this->logisticsGroup($department)?->getId();
    }

    private function isAnlassPotPayer(Department $department, ?string $payerGroupId): bool
    {
        $logisticsId = $this->logisticsGroup($department)?->getId();
        if ($logisticsId !== null) {
            return $payerGroupId === $logisticsId;
        }

        return $payerGroupId === null;
    }

    private function syncFinanceSpiegel(Department $department, ?string $rahmenChf): void
    {
        $finance = $this->entityManager->find(ActivityGrossanlassProcurementFinance::class, $department->getId());
        if (!$finance instanceof ActivityGrossanlassProcurementFinance) {
            if ($rahmenChf === null) {
                return;
            }
            $finance = new ActivityGrossanlassProcurementFinance();
            $finance->setDepartment($department);
            $this->entityManager->persist($finance);
        }
        $finance->setRahmenChf($rahmenChf);
        $finance->touchUpdatedAt();
    }

    private function backfillMissingLineCosts(Department $department): void
    {
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(['departmentId' => $department->getId()]);
        $created = false;
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            if ($this->findMainForLine($line) instanceof DepartmentGrossanlassCost) {
                continue;
            }
            $this->ensureMainForLine($line);
            $created = true;
        }
        if ($created) {
            $this->entityManager->flush();
        }
    }

    private function findMainForLine(ActivityGrossanlassProcurementLine $line): ?DepartmentGrossanlassCost
    {
        return $this->findMainForLineId($line->getDepartmentId(), $line->getId());
    }

    private function findMainForLineId(string $departmentId, string $lineId): ?DepartmentGrossanlassCost
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)
            ->findBy(['departmentId' => $departmentId, 'procurementLineId' => $lineId]);
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassCost && $row->isMainKind()) {
                return $row;
            }
        }

        return null;
    }

    private function findMainForCommitment(DepartmentGrossanlassCommitment $commitment): ?DepartmentGrossanlassCost
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)
            ->findBy(['departmentId' => $commitment->getDepartmentId(), 'commitmentId' => $commitment->getId()]);
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassCost && $row->isMainKind()) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<ActivityGrossanlassProcurementCategory>
     */
    private function loadCategories(Department $department): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->findBy(['departmentId' => $department->getId()], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        return array_values(array_filter($rows, static fn ($row) => $row instanceof ActivityGrossanlassProcurementCategory));
    }

    private function findInDepartment(Department $department, string $id): DepartmentGrossanlassCost
    {
        $row = $this->entityManager->find(DepartmentGrossanlassCost::class, $id);
        if (!$row instanceof DepartmentGrossanlassCost || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Kostenzeile nicht gefunden');
        }

        return $row;
    }

    private function findGroupInDepartment(Department $department, string $groupId): Group
    {
        $group = $this->entityManager->find(Group::class, $groupId);
        if (!$group instanceof Group || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ressort nicht gefunden');
        }

        return $group;
    }

    private function findLineInDepartment(Department $department, string $lineId): ActivityGrossanlassProcurementLine
    {
        $line = $this->entityManager->find(ActivityGrossanlassProcurementLine::class, $lineId);
        if (!$line instanceof ActivityGrossanlassProcurementLine || $line->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Beschaffungsposition nicht gefunden');
        }

        return $line;
    }

    private function findCommitmentInDepartment(Department $department, string $id): DepartmentGrossanlassCommitment
    {
        $row = $this->entityManager->find(DepartmentGrossanlassCommitment::class, $id);
        if (!$row instanceof DepartmentGrossanlassCommitment || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Zusage nicht gefunden');
        }

        return $row;
    }

    private function findCategoryInDepartment(Department $department, string $categoryId): ActivityGrossanlassProcurementCategory
    {
        $category = $this->entityManager->find(ActivityGrossanlassProcurementCategory::class, $categoryId);
        if (!$category instanceof ActivityGrossanlassProcurementCategory || $category->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Kategorie nicht gefunden');
        }

        return $category;
    }

    private function findAddressInDepartment(Department $department, string $addressId): Address
    {
        $address = $this->entityManager->find(Address::class, $addressId);
        if (!$address instanceof Address || $address->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Kontakt nicht gefunden');
        }

        return $address;
    }

    private function parseOptionalAmountChf(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $amount = is_numeric($value) ? (float) $value : (float) str_replace(["'", ' '], '', str_replace(',', '.', (string) $value));
        if ($amount < 0) {
            throw new \InvalidArgumentException('Betrag darf nicht negativ sein');
        }

        return number_format($amount, 2, '.', '');
    }

    private function decimalToFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function assertCanRead(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if ($this->access->canManagePlanung($user, $department)) {
            return;
        }
        if ($this->access->resolveAssignedGroupBranchIds($user, $department->getId()) !== []) {
            return;
        }

        throw new \RuntimeException('Keine Berechtigung für Kosten');
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Kosten');
        }
    }
}
