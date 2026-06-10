<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use App\Entity\Activity;
use App\Entity\MaterialComboOption;
use App\Entity\WorkshopTicket;
use App\Service\ComboResolutionService;
use App\Service\MaterialAvailabilityReservationQuery;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Material Availability Controller
 * 
 * Liefert zeitraum-basierte Verfügbarkeit für Aktivitäts-Planung.
 * Berücksichtigt Gesamtbestand (Batches) minus Reservierungen:
 * - Bestellung (activity_item) bei Zeitraum-Overlap (Entwurf…Bestätigt)
 * - Physische Pipeline (activity_pack_item) ab «Wird gepackt» bis eingelagert;
 *   bei Zeitraum-Abfrage nur bei Overlap mit Planungszeitraum der blockierenden Aktivität
 */
class MaterialAvailabilityController extends AbstractController
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private ComboResolutionService $comboResolution,
    ) {}

    /**
     * Suchbegriffe (Leerzeichen getrennt): alle müssen als Teilstring vorkommen; Groß-/Kleinschreibung egal (inkl. Umlaute).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function materialNameSearchWhereAndParams(string $search, bool $isPostgres): array
    {
        $tokens = array_values(array_filter(
            array_map('trim', preg_split('/\s+/u', $search) ?: []),
            static fn (string $t): bool => $t !== ''
        ));
        if ($tokens === []) {
            return ['', []];
        }

        $parts = [];
        $params = [];
        foreach ($tokens as $i => $token) {
            $needle = mb_strtolower($token, 'UTF-8');
            $key = 'nm_tok_' . $i;
            $params[$key] = $needle;
            if ($isPostgres) {
                $parts[] = 'strpos(LOWER(COALESCE(avail.name, \'\')), :' . $key . ') > 0';
            } else {
                $parts[] = 'LOCATE(:' . $key . ', LOWER(COALESCE(avail.name, \'\'))) > 0';
            }
        }

        return [' AND (' . implode(' AND ', $parts) . ')', $params];
    }

    /**
     * GET /api/materials/available-for-period
     * 
     * Parameter:
     * - departmentId (required)
     * - startDate (optional, ISO 8601 DateTime – ohne Datum wird Gesamtbestand zurückgegeben)
     * - endDate (optional, ISO 8601 DateTime – ohne Datum wird Gesamtbestand zurückgegeben)
     * - search (optional, filtert nach Materialname, ab 1 Zeichen; leer = erste Treffer ohne Namensfilter)
     * - materialItemIds (optional, Komma-getrennte IDs; max. 50 — Ergebnis auf diese Material-Items begrenzen)
     * - excludeActivityId (optional, um eigene Reservierungen auszuschliessen)
     * - limit (optional, default 20)
     * - internalScope (optional, nur bei source=internal): own | invited | both | single
     *   own = nur eigenes Department; invited = nur eingeladene Departments (alle mit Status angenommen);
     *   both = eigenes + alle eingeladenen (Default, wie bisher);
     *   single = genau ein Department (Parameter singleDepartmentId, muss eigenes oder angenommenes Einlad-Dept. sein)
     * 
     * Response: Array von Materialien mit Verfügbarkeit im Zeitraum (oder Gesamtbestand ohne Zeitraum)
     */
    #[Route('/api/materials/available-for-period', name: 'api_materials_available_for_period', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAvailableForPeriod(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('departmentId');
        $startDateTime = $request->query->get('startDate');
        $endDateTime = $request->query->get('endDate');
        $search = trim($request->query->get('search', ''));
        /** @var list<string> */
        $materialItemUuidList = [];
        $materialItemIdsRaw = (string) $request->query->get('materialItemIds', '');
        if ($materialItemIdsRaw !== '') {
            foreach (explode(',', $materialItemIdsRaw) as $part) {
                $id = trim($part);
                if ($id === '') {
                    continue;
                }
                if (IdGenerator::isValid($id) || preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/i', $id) === 1) {
                    $materialItemUuidList[] = strtolower($id);
                }
            }
            $materialItemUuidList = array_values(array_unique($materialItemUuidList));
            if (count($materialItemUuidList) > 50) {
                $materialItemUuidList = array_slice($materialItemUuidList, 0, 50);
            }
        }
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        if ($materialItemUuidList !== []) {
            $limit = min(50, max($limit, count($materialItemUuidList)));
        }
        $source = strtolower((string) $request->query->get('source', 'all'));
        $includeGlobalJs = filter_var($request->query->get('includeGlobalJs', '1'), FILTER_VALIDATE_BOOLEAN);
        $activityId = trim((string) $request->query->get('activityId', ''));
        $excludeActivityId = trim((string) $request->query->get('excludeActivityId', ''));

        if (!$departmentId) {
            return new JsonResponse([
                'error' => 'Fehlender Parameter: departmentId'
            ], 400);
        }

        if (!in_array($source, ['all', 'internal', 'js'], true)) {
            $source = 'all';
        }

        // Datum parsen (optional – ohne Datum wird Gesamtbestand zurückgegeben)
        $hasPeriod = $startDateTime && $endDateTime;
        $startDate = null;
        $endDate = null;

        if ($hasPeriod) {
            try {
                $startDate = new \DateTime($startDateTime);
                $endDate = new \DateTime($endDateTime);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'error' => 'Ungültiges Datumsformat. Verwende ISO 8601 (z.B. 2026-02-08T14:00:00)'
                ], 400);
            }
        }

        try {
            $allowedDepartmentIds = [$departmentId];
            if ($activityId !== '') {
                $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
                if ($activity && !$activity->isDeleted() && $activity->getDepartmentId() === $departmentId) {
                    $invites = $activity->getInvitedDepartments() ?? [];
                    foreach ($invites as $entry) {
                        if (!is_array($entry)) continue;
                        $inviteDeptId = trim((string) ($entry['id'] ?? ''));
                        $status = (string) ($entry['status'] ?? 'pending');
                        if ($inviteDeptId !== '' && $status === 'accepted') {
                            $allowedDepartmentIds[] = $inviteDeptId;
                        }
                    }
                }
            }
            $allowedDepartmentIds = array_values(array_unique($allowedDepartmentIds));
            $invitedOnlyIds = array_values(array_diff($allowedDepartmentIds, [$departmentId]));

            $internalScope = strtolower((string) $request->query->get('internalScope', 'both'));
            if (!in_array($internalScope, ['own', 'invited', 'both', 'single'], true)) {
                $internalScope = 'both';
            }

            $deptPlaceholders = [];
            $deptParams = [];
            foreach ($allowedDepartmentIds as $idx => $allowedId) {
                $key = 'allowed_dept_' . $idx;
                $deptPlaceholders[] = ':' . $key;
                $deptParams[$key] = $allowedId;
            }
            $allowedDeptSql = implode(', ', $deptPlaceholders);

            if ($source === 'internal') {
                if ($internalScope === 'own') {
                    $scopeWhere = 'mi.department_id = :scope_own_department AND COALESCE(mi.is_js_material, FALSE) = FALSE';
                    $deptParams = ['scope_own_department' => $departmentId];
                } elseif ($internalScope === 'invited') {
                    if ($invitedOnlyIds === []) {
                        $scopeWhere = 'FALSE';
                        $deptParams = [];
                    } else {
                        $deptParams = [];
                        $invPlaceholders = [];
                        foreach ($invitedOnlyIds as $idx => $invId) {
                            $key = 'invited_dept_' . $idx;
                            $invPlaceholders[] = ':' . $key;
                            $deptParams[$key] = $invId;
                        }
                        $invSql = implode(', ', $invPlaceholders);
                        $scopeWhere = "mi.department_id IN ($invSql) AND COALESCE(mi.is_js_material, FALSE) = FALSE";
                    }
                } elseif ($internalScope === 'single') {
                    $singleId = trim((string) $request->query->get('singleDepartmentId', ''));
                    if ($singleId === '' || !in_array($singleId, $allowedDepartmentIds, true)) {
                        return new JsonResponse(['error' => 'Ungültiges singleDepartmentId'], 400);
                    }
                    $scopeWhere = 'mi.department_id = :scope_single_department AND COALESCE(mi.is_js_material, FALSE) = FALSE';
                    $deptParams = ['scope_single_department' => $singleId];
                } else {
                    $scopeWhere = "mi.department_id IN ($allowedDeptSql) AND COALESCE(mi.is_js_material, FALSE) = FALSE";
                }
            } elseif ($source === 'js') {
                $scopeWhere = $includeGlobalJs
                    ? 'COALESCE(mi.is_js_material, FALSE) = TRUE'
                    : "mi.department_id IN ($allowedDeptSql) AND COALESCE(mi.is_js_material, FALSE) = TRUE";
            } else {
                $scopeWhere = $includeGlobalJs
                    ? "(mi.department_id IN ($allowedDeptSql) OR COALESCE(mi.is_js_material, FALSE) = TRUE)"
                    : "mi.department_id IN ($allowedDeptSql)";
            }

            $materialIdFilterSql = '';
            $materialIdFilterParams = [];
            if ($materialItemUuidList !== []) {
                $midPh = [];
                foreach ($materialItemUuidList as $idx => $uuid) {
                    $k = 'mat_filter_' . $idx;
                    $midPh[] = ':' . $k;
                    $materialIdFilterParams[$k] = $uuid;
                }
                $materialIdFilterSql = ' AND mi.id IN (' . implode(', ', $midPh) . ')';
            }

            $reservedExcludeSql = $excludeActivityId !== ''
                ? ' AND a.id != :exclude_activity_id'
                : '';

            if ($hasPeriod) {
                // Zeitraum-basierte Verfügbarkeit (Department + globales J&S Material)
                $sql = "SELECT 
                        mi.id AS material_item_id,
                        mi.name,
                        mi.category_id,
                        mi.material_type,
                        mi.department_id AS source_department_id,
                        d.name AS source_department_name,
                        COALESCE(batch_totals.total_qty, 0)::INT AS total_stock,
                        COALESCE(repair_totals.qty_in_repair, 0)::INT AS stock_in_repair,
                        COALESCE(stock_in_phys_combo.qty_in_phys_combo, 0)::INT AS stock_in_phys_combo_kisten,
                        COALESCE(stock_in_storage.qty_in_storage, 0)::INT AS stock_in_storage_containers,
                        COALESCE(reserved.reserved_qty, 0)::INT AS reserved_in_activities,
                        GREATEST(0,
                            CASE WHEN mi.material_type = 'physical_combo' THEN
                                COALESCE(batch_totals.total_qty, 0) - COALESCE(reserved.reserved_qty, 0)
                            ELSE
                                COALESCE(batch_totals.total_qty, 0) - COALESCE(stock_in_phys_combo.qty_in_phys_combo, 0) - COALESCE(reserved.reserved_qty, 0)
                            END
                        )::INT AS available_for_period
                        FROM material_item mi
                        JOIN department d ON d.id = mi.department_id
                        LEFT JOIN (
                            SELECT material_item_id AS mid, SUM(qty) AS total_qty
                            FROM material_batch
                            WHERE status = 'active'
                            GROUP BY material_item_id
                        ) batch_totals ON batch_totals.mid = mi.id
                        LEFT JOIN (
                            SELECT material_item_id AS mid, SUM(qty) AS qty_in_repair
                            FROM material_batch
                            WHERE status = 'repair'
                            GROUP BY material_item_id
                        ) repair_totals ON repair_totals.mid = mi.id
                        LEFT JOIN (
                            SELECT b.material_item_id AS mid, SUM(a.qty) AS qty_in_phys_combo
                            FROM batch_storage_allocation a
                            INNER JOIN material_batch b ON a.batch_id = b.id AND b.status = 'active'
                            INNER JOIN material_item combo_kiste ON combo_kiste.linked_container_batch_id = a.container_batch_id
                                AND combo_kiste.material_type = 'physical_combo'
                                AND combo_kiste.deleted_at IS NULL
                            GROUP BY b.material_item_id
                        ) stock_in_phys_combo ON stock_in_phys_combo.mid = mi.id
                        LEFT JOIN (
                            SELECT b.material_item_id AS mid, SUM(a.qty) AS qty_in_storage
                            FROM batch_storage_allocation a
                            INNER JOIN material_batch b ON a.batch_id = b.id AND b.status = 'active'
                            WHERE a.container_batch_id IS NOT NULL
                              AND NOT EXISTS (
                                  SELECT 1 FROM material_item combo_kiste
                                  WHERE combo_kiste.linked_container_batch_id = a.container_batch_id
                                    AND combo_kiste.material_type = 'physical_combo'
                                    AND combo_kiste.deleted_at IS NULL
                              )
                            GROUP BY b.material_item_id
                        ) stock_in_storage ON stock_in_storage.mid = mi.id
                        " . MaterialAvailabilityReservationQuery::lateralReservedQtySql(true, $reservedExcludeSql) . "
                        WHERE mi.deleted_at IS NULL
                          AND mi.combo_status <> 'draft'
                          AND $scopeWhere $materialIdFilterSql
                          " . $this->excludeSelfProvidedOnlyComponentsSql();

                $params = array_merge([
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                ], $deptParams, $materialIdFilterParams);
                if ($excludeActivityId !== '') {
                    $params['exclude_activity_id'] = $excludeActivityId;
                }
            } else {
                // Ohne Zeitraum: Gesamtbestand minus physische Pipeline-Sperre (keine Bestell-Reservierung)
                $sql = "SELECT mi.id AS material_item_id, mi.name, mi.category_id, mi.material_type, mi.department_id AS source_department_id, d.name AS source_department_name,
                        COALESCE(SUM(mb.qty), 0) AS total_stock,
                        COALESCE(MAX(repair_totals.qty_in_repair), 0) AS stock_in_repair,
                        COALESCE(MAX(stock_in_phys_combo.qty_in_phys_combo), 0) AS stock_in_phys_combo_kisten,
                        COALESCE(MAX(stock_in_storage.qty_in_storage), 0) AS stock_in_storage_containers,
                        COALESCE(MAX(reserved.reserved_qty), 0) AS reserved_in_activities,
                        GREATEST(0,
                            CASE WHEN mi.material_type = 'physical_combo' THEN
                                COALESCE(SUM(mb.qty), 0) - COALESCE(MAX(reserved.reserved_qty), 0)
                            ELSE
                                COALESCE(SUM(mb.qty), 0) - COALESCE(MAX(stock_in_phys_combo.qty_in_phys_combo), 0) - COALESCE(MAX(reserved.reserved_qty), 0)
                            END
                        ) AS available_for_period
                        FROM material_item mi
                        JOIN department d ON d.id = mi.department_id
                        LEFT JOIN material_batch mb ON mb.material_item_id = mi.id AND mb.status = 'active'
                        LEFT JOIN (
                            SELECT material_item_id AS mid, SUM(qty) AS qty_in_repair
                            FROM material_batch
                            WHERE status = 'repair'
                            GROUP BY material_item_id
                        ) repair_totals ON repair_totals.mid = mi.id
                        LEFT JOIN (
                            SELECT b.material_item_id AS mid, SUM(a.qty) AS qty_in_phys_combo
                            FROM batch_storage_allocation a
                            INNER JOIN material_batch b ON a.batch_id = b.id AND b.status = 'active'
                            INNER JOIN material_item combo_kiste ON combo_kiste.linked_container_batch_id = a.container_batch_id
                                AND combo_kiste.material_type = 'physical_combo'
                                AND combo_kiste.deleted_at IS NULL
                            GROUP BY b.material_item_id
                        ) stock_in_phys_combo ON stock_in_phys_combo.mid = mi.id
                        LEFT JOIN (
                            SELECT b.material_item_id AS mid, SUM(a.qty) AS qty_in_storage
                            FROM batch_storage_allocation a
                            INNER JOIN material_batch b ON a.batch_id = b.id AND b.status = 'active'
                            WHERE a.container_batch_id IS NOT NULL
                              AND NOT EXISTS (
                                  SELECT 1 FROM material_item combo_kiste
                                  WHERE combo_kiste.linked_container_batch_id = a.container_batch_id
                                    AND combo_kiste.material_type = 'physical_combo'
                                    AND combo_kiste.deleted_at IS NULL
                              )
                            GROUP BY b.material_item_id
                        ) stock_in_storage ON stock_in_storage.mid = mi.id
                        " . MaterialAvailabilityReservationQuery::lateralReservedQtySql(false, $reservedExcludeSql) . "
                        WHERE mi.deleted_at IS NULL
                          AND mi.combo_status <> 'draft'
                          AND $scopeWhere $materialIdFilterSql
                          " . $this->excludeSelfProvidedOnlyComponentsSql() . "
                        GROUP BY mi.id, mi.name, mi.category_id, mi.material_type, mi.department_id, d.name, reserved.reserved_qty";

                $params = array_merge($deptParams, $materialIdFilterParams);
            }

            $isPostgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

            // Such-Filter: Teilstrings (z. B. "ze" in "Grünes Zelt"), mehrere Wörter = alle müssen vorkommen; Groß-/Kleinschreibung egal
            if ($search !== '') {
                [$nameWhere, $nameParams] = $this->materialNameSearchWhereAndParams($search, $isPostgres);
                if ($nameWhere === '') {
                    $sql = "SELECT * FROM ($sql) AS avail ORDER BY avail.name LIMIT :limit";
                    $params['limit'] = $limit;
                } else {
                    $sql = "SELECT * FROM ($sql) AS avail WHERE 1=1 {$nameWhere} ORDER BY avail.name LIMIT :limit";
                    $params = array_merge($params, $nameParams);
                    $params['limit'] = $limit;
                }
            } else {
                $sql = "SELECT * FROM ($sql) AS avail ORDER BY avail.name LIMIT :limit";
                $params['limit'] = $limit;
            }

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery($params);

            $materials = $result->fetchAllAssociative();

            // Preis- und Verbrauchsmaterial-Info aus material_item dazuladen
            $materialIds = array_column($materials, 'material_item_id');
            $priceMap = [];
            if (!empty($materialIds)) {
                $placeholders = implode(',', array_map(fn($i) => ':mid' . $i, array_keys($materialIds)));
                $priceSql = "SELECT id, material_type, is_consumable, sale_price, rental_price_day, rental_price_week, rental_price_month, pack_size, pack_unit, is_js_material, external_source FROM material_item WHERE id IN ($placeholders)";
                $priceParams = [];
                foreach ($materialIds as $i => $mid) {
                    $priceParams['mid' . $i] = $mid;
                }
                $priceStmt = $this->connection->prepare($priceSql);
                $priceResult = $priceStmt->executeQuery($priceParams);
                foreach ($priceResult->fetchAllAssociative() as $row) {
                    $priceMap[$row['id']] = $row;
                }
            }

            $linkedContainerMap = [];
            $comboIds = [];
            foreach ($materials as $row) {
                $mid = $row['material_item_id'];
                $type = $row['material_type'] ?? ($priceMap[$mid]['material_type'] ?? '');
                if ($type === 'physical_combo') {
                    $comboIds[] = $mid;
                }
            }
            $comboIds = array_values(array_unique($comboIds));
            if ($comboIds !== []) {
                $lcPh = implode(',', array_map(fn ($i) => ':lc_mid' . $i, array_keys($comboIds)));
                $lcParams = [];
                foreach ($comboIds as $i => $mid) {
                    $lcParams['lc_mid' . $i] = $mid;
                }
                $lcSql = "SELECT combo.id AS combo_id,
                        NULLIF(TRIM(lcb.label), '') AS linked_container_label,
                        NULLIF(TRIM(lcb.serial_number), '') AS linked_container_serial,
                        lcb_mi.pack_unit AS linked_container_pack_unit
                    FROM material_item combo
                    LEFT JOIN material_batch lcb ON lcb.id = combo.linked_container_batch_id
                    LEFT JOIN material_item lcb_mi ON lcb_mi.id = lcb.material_item_id
                    WHERE combo.id IN ($lcPh)";
                $lcStmt = $this->connection->prepare($lcSql);
                $lcRows = $lcStmt->executeQuery($lcParams)->fetchAllAssociative();
                foreach ($lcRows as $lc) {
                    $label = trim((string) ($lc['linked_container_label'] ?? ''));
                    $serial = trim((string) ($lc['linked_container_serial'] ?? ''));
                    $linkedContainerMap[$lc['combo_id']] = [
                        'label' => $label !== '' ? $label : ($serial !== '' ? $serial : null),
                        'pack_unit' => $lc['linked_container_pack_unit'] ?? null,
                    ];
                }

                // Ohne Referenz-Batch: Behälter-Komponente der Stückliste (z. B. Transporttasche mit pack_unit «Sack»)
                $comboIdsNeedingFallback = array_values(array_filter(
                    $comboIds,
                    static fn (string $cid) => trim((string) ($linkedContainerMap[$cid]['pack_unit'] ?? '')) === '',
                ));
                if ($comboIdsNeedingFallback !== []) {
                    $fbPh = implode(',', array_map(fn ($i) => ':fb_mid' . $i, array_keys($comboIdsNeedingFallback)));
                    $fbParams = [];
                    foreach ($comboIdsNeedingFallback as $i => $mid) {
                        $fbParams['fb_mid' . $i] = $mid;
                    }
                    $fbSql = "SELECT cc.parent_material_id AS combo_id,
                            mi.name AS component_name,
                            mi.pack_unit AS component_pack_unit,
                            mi.is_container AS is_container
                        FROM material_combo_component cc
                        INNER JOIN material_item mi ON mi.id = cc.component_material_id AND mi.deleted_at IS NULL
                        WHERE cc.parent_material_id IN ($fbPh)
                          AND (mi.is_container = true OR (mi.pack_unit IS NOT NULL AND TRIM(mi.pack_unit) <> ''))";
                    $fbRows = $this->connection->prepare($fbSql)->executeQuery($fbParams)->fetchAllAssociative();
                    $grouped = [];
                    foreach ($fbRows as $fb) {
                        $grouped[$fb['combo_id']][] = $fb;
                    }
                    foreach ($comboIdsNeedingFallback as $cid) {
                        $picked = $this->pickComboShellComponentForDisplay($grouped[$cid] ?? []);
                        if ($picked === null) {
                            continue;
                        }
                        $existing = $linkedContainerMap[$cid] ?? ['label' => null, 'pack_unit' => null];
                        $linkedContainerMap[$cid] = [
                            'label' => $existing['label'] ?? trim((string) ($picked['component_name'] ?? '')) ?: null,
                            'pack_unit' => $picked['component_pack_unit'] ?? null,
                        ];
                    }
                }
            }

            // camelCase für Frontend
            $materials = array_map(function ($item) use ($priceMap, $linkedContainerMap) {
                $priceInfo = $priceMap[$item['material_item_id']] ?? [];
                $linked = $linkedContainerMap[$item['material_item_id']] ?? null;
                return [
                    'materialItemId' => $item['material_item_id'],
                    'name' => $item['name'],
                    'categoryId' => $item['category_id'],
                    'sourceDepartmentId' => $item['source_department_id'] ?? null,
                    'sourceDepartmentName' => $item['source_department_name'] ?? null,
                    'totalStock' => (int) $item['total_stock'],
                    'stockInRepair' => (int) ($item['stock_in_repair'] ?? 0),
                    'stockInPhysComboKisten' => (int) ($item['stock_in_phys_combo_kisten'] ?? 0),
                    'stockInStorageContainers' => (int) ($item['stock_in_storage_containers'] ?? 0),
                    /** @deprecated use stockInPhysComboKisten / stockInStorageContainers */
                    'stockInContainers' => (int) ($item['stock_in_phys_combo_kisten'] ?? 0),
                    'reservedInActivities' => (int) $item['reserved_in_activities'],
                    'availableForPeriod' => (int) $item['available_for_period'],
                    'materialType' => $item['material_type'] ?? ($priceInfo['material_type'] ?? 'physical'),
                    'isConsumable' => (bool) ($priceInfo['is_consumable'] ?? false),
                    'salePrice' => $priceInfo['sale_price'] ?? null,
                    'rentalPriceDay' => $priceInfo['rental_price_day'] ?? null,
                    'rentalPriceWeek' => $priceInfo['rental_price_week'] ?? null,
                    'rentalPriceMonth' => $priceInfo['rental_price_month'] ?? null,
                    'packSize' => isset($priceInfo['pack_size']) ? (int) $priceInfo['pack_size'] : null,
                    'packUnit' => $priceInfo['pack_unit'] ?? null,
                    'isJsMaterial' => (bool) ($priceInfo['is_js_material'] ?? false),
                    'externalSource' => $priceInfo['external_source'] ?? null,
                    'linkedContainerLabel' => $linked['label'] ?? null,
                    'linkedContainerPackUnit' => $linked['pack_unit'] ?? null,
                ];
            }, $materials);

            // Virtuelle Kombos: Verfügbarkeit = Flaschenhals min(floor(frei/menge)) über stock-Teile.
            $materials = $this->enrichVirtualComboAvailability(
                $materials,
                $hasPeriod ? $startDate : null,
                $hasPeriod ? $endDate : null,
                $excludeActivityId,
            );

            $materials = $this->enrichPhysicalComboComponentMembership($materials);

            $materials = $this->enrichPhysicalComboOwnCrateCounts($materials);

            $materials = $this->finalizeAvailabilityForPeriod(
                $materials,
                $departmentId,
                $hasPeriod ? $startDate : null,
                $hasPeriod ? $endDate : null,
                $excludeActivityId,
            );

            return new JsonResponse($materials);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Datenbankfehler: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Konfigurator-Verfügbarkeit (Paket 6): Basis-Flaschenhals + 3-Zustands-Modell pro Option
     * (nur stock-Teile, im Zeitraum, × Bestellmenge) + Auflösung der aktuellen Auswahl
     * (für „live X× verfügbar"). README Abschnitt 6 „Option = drei Zustände".
     */
    #[Route('/api/materials/{comboId}/configurator-availability', name: 'api_materials_configurator_availability', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function configuratorAvailability(string $comboId, Request $request): JsonResponse
    {
        $combo = $this->entityManager->getRepository(\App\Entity\MaterialItem::class)->find($comboId);
        if (!$combo || $combo->getDeletedAt() !== null) {
            return new JsonResponse(['error' => 'Kombo nicht gefunden'], 404);
        }
        if ($combo->getMaterialType() !== 'virtual_combo') {
            return new JsonResponse(['error' => 'Konfigurator-Verfügbarkeit nur für virtuelle Kombos'], 400);
        }

        $quantity = max(1, (int) $request->query->get('quantity', 1));
        $excludeActivityId = trim((string) $request->query->get('excludeActivityId', ''));
        $startDateTime = $request->query->get('startDate');
        $endDateTime = $request->query->get('endDate');
        $startDate = null;
        $endDate = null;
        if ($startDateTime && $endDateTime) {
            try {
                $startDate = new \DateTime((string) $startDateTime);
                $endDate = new \DateTime((string) $endDateTime);
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Ungültiges Datumsformat'], 400);
            }
        }

        $selectedOptionIds = [];
        $selRaw = (string) $request->query->get('selectedOptionIds', '');
        if ($selRaw !== '') {
            foreach (explode(',', $selRaw) as $part) {
                $v = trim($part);
                if ($v !== '') {
                    $selectedOptionIds[] = $v;
                }
            }
        }

        // Alle referenzierten Teile (Basis-Pflichtteile + alle Options-Deltas) für die Verfügbarkeits-Query sammeln.
        $base = $this->comboResolution->resolve($comboId, []);
        $groups = $this->entityManager->getRepository(\App\Entity\MaterialComboOptionGroup::class)
            ->findBy(['materialItemId' => $comboId], ['sortOrder' => 'ASC']);
        $options = $this->entityManager->getRepository(MaterialComboOption::class)
            ->findBy(['materialItemId' => $comboId], ['sortOrder' => 'ASC']);

        $allIds = [];
        foreach ($base['stock'] as $mid => $_p) {
            $allIds[(string) $mid] = true;
        }
        /** @var array<string, list<MaterialComboOptionDelta>> $deltasByOption */
        $deltasByOption = [];
        foreach ($options as $opt) {
            $deltas = $this->entityManager->getRepository(MaterialComboOptionDelta::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.componentMaterial', 'cm')
                ->addSelect('cm')
                ->where('d.optionId = :oid')
                ->setParameter('oid', $opt->getId())
                ->orderBy('d.sortOrder', 'ASC')
                ->getQuery()
                ->getResult();
            $deltasByOption[(string) $opt->getId()] = $deltas;
            foreach ($deltas as $d) {
                if ($d->getComponentSource() !== 'self_provided') {
                    $allIds[(string) $d->getComponentMaterialId()] = true;
                }
            }
        }

        $availMap = $this->availabilityForIds(array_keys($allIds), $startDate, $endDate, $excludeActivityId);
        $totalMap = $this->totalStockForIds(array_keys($allIds));

        // Basis-Flaschenhals (Pflichtteile). Pflicht-Basis fehlt/0 ⇒ ganze Kombo nicht baubar.
        $baseComponents = [];
        $baseBottleneck = null;
        $baseBlocked = false;
        foreach ($base['stock'] as $mid => $part) {
            $mid = (string) $mid;
            $qtyPer = (int) $part['qty_per_combo'];
            if ($qtyPer <= 0) {
                continue;
            }
            $avail = (int) ($availMap[$mid] ?? 0);
            $inStock = ((int) ($totalMap[$mid] ?? 0)) > 0;
            $possible = intdiv($avail, $qtyPer);
            $baseBottleneck = $baseBottleneck === null ? $possible : min($baseBottleneck, $possible);
            if (!$inStock || $possible < $quantity) {
                $baseBlocked = true;
            }
            $baseComponents[] = [
                'materialItemId' => $mid,
                'name' => $part['name'],
                'qtyPerCombo' => $qtyPer,
                'availableForPeriod' => $avail,
                'inStock' => $inStock,
            ];
        }

        // 3-Zustands-Modell je Option (nur additive stock-Teile bestimmen die Sperre).
        $optionResults = [];
        foreach ($options as $opt) {
            $oid = (string) $opt->getId();
            $added = [];
            $optBottleneck = null;
            $state = 'available';
            foreach ($deltasByOption[$oid] as $d) {
                if ($d->getComponentSource() === 'self_provided') {
                    continue;
                }
                $delta = $d->getQtyDelta();
                if ($delta <= 0) {
                    continue; // reine Abzüge brauchen keine Sperre
                }
                $mid = (string) $d->getComponentMaterialId();
                $avail = (int) ($availMap[$mid] ?? 0);
                $inStock = ((int) ($totalMap[$mid] ?? 0)) > 0;
                $cm = $d->getComponentMaterial();
                $possible = intdiv($avail, $delta);
                $optBottleneck = $optBottleneck === null ? $possible : min($optBottleneck, $possible);
                if (!$inStock && $state !== 'missing') {
                    $state = 'missing';
                }
                $added[] = [
                    'materialItemId' => $mid,
                    'name' => $cm->getName(),
                    'qtyDelta' => $delta,
                    'availableForPeriod' => $avail,
                    'inStock' => $inStock,
                ];
            }
            if ($state !== 'missing' && $optBottleneck !== null && $optBottleneck < $quantity) {
                $state = 'blocked';
            }
            $optionResults[] = [
                'optionId' => $oid,
                'name' => $opt->getName(),
                'displayMode' => $opt->getDisplayMode(),
                'optionGroupId' => $opt->getOptionGroupId(),
                'defaultSelected' => $opt->getDefaultSelected(),
                'state' => $state,
                'buildable' => $optBottleneck,
                'addedStockComponents' => $added,
            ];
        }

        // Auflösung der aktuellen Auswahl → live Flaschenhals × Bestellmenge.
        $resolved = $this->comboResolution->resolve($comboId, $selectedOptionIds);
        $selBottleneck = null;
        $selBlocked = false;
        $selComponents = [];
        foreach ($resolved['stock'] as $mid => $part) {
            $mid = (string) $mid;
            $qtyPer = (int) $part['qty_per_combo'];
            if ($qtyPer <= 0) {
                continue;
            }
            $avail = (int) ($availMap[$mid] ?? 0);
            $inStock = ((int) ($totalMap[$mid] ?? 0)) > 0;
            $possible = intdiv($avail, $qtyPer);
            $selBottleneck = $selBottleneck === null ? $possible : min($selBottleneck, $possible);
            if (!$inStock || $possible < $quantity) {
                $selBlocked = true;
            }
            $selComponents[] = [
                'materialItemId' => $mid,
                'name' => $part['name'],
                'qtyPerCombo' => $qtyPer,
                'availableForPeriod' => $avail,
                'inStock' => $inStock,
            ];
        }

        $groupResults = array_map(static fn (\App\Entity\MaterialComboOptionGroup $g) => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'selectionType' => $g->getSelectionType(),
            'minSelect' => $g->getMinSelect(),
            'maxSelect' => $g->getMaxSelect(),
            'sortOrder' => $g->getSortOrder(),
        ], $groups);

        return new JsonResponse([
            'comboId' => $comboId,
            'quantity' => $quantity,
            'groups' => $groupResults,
            'base' => [
                'components' => $baseComponents,
                'buildable' => $baseBottleneck,
                'blocked' => $baseBlocked,
            ],
            'options' => $optionResults,
            'selected' => [
                'selectedOptionIds' => array_values($selectedOptionIds),
                'components' => $selComponents,
                'buildable' => $selBottleneck,
                'blocked' => $selBlocked,
                'selfProvided' => array_values(array_map(static fn ($p) => [
                    'materialItemId' => (string) $p['component_material_id'],
                    'name' => $p['name'],
                    'qtyPerCombo' => (int) $p['qty_per_combo'],
                ], $resolved['self_provided'])),
            ],
        ]);
    }

    /**
     * Gesamtbestand (aktive Batches) je Material-Id – um „nicht im Bestand" (total 0) vom Flaschenhals 0 zu trennen.
     *
     * @param list<string> $ids
     * @return array<string, int>
     */
    private function totalStockForIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => (string) $v !== '')));
        if ($ids === []) {
            return [];
        }
        $idPh = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'ts_id' . $i;
            $idPh[] = ':' . $k;
            $params[$k] = $id;
        }
        $sql = 'SELECT material_item_id AS mid, COALESCE(SUM(qty), 0) AS total_qty
                FROM material_batch
                WHERE status = \'active\' AND material_item_id IN (' . implode(', ', $idPh) . ')
                GROUP BY material_item_id';
        $rows = $this->connection->prepare($sql)->executeQuery($params)->fetchAllAssociative();
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['mid']] = (int) $r['total_qty'];
        }
        return $map;
    }

    /**
     * «Selbst mitbringen»-Stubs: nur Stücklisten-Hinweis, nicht buchbar/suchbar.
     */
    private function excludeSelfProvidedOnlyComponentsSql(): string
    {
        return <<<'SQL'

                          AND NOT EXISTS (
                              SELECT 1 FROM material_combo_component cc_sp
                              WHERE cc_sp.component_material_id = mi.id
                                AND cc_sp.component_source = 'self_provided'
                                AND NOT EXISTS (
                                    SELECT 1 FROM material_combo_component cc_st
                                    WHERE cc_st.component_material_id = mi.id
                                      AND cc_st.component_source = 'stock'
                                )
                          )
        SQL;
    }

    /**
     * Reichert virtuelle Kombos mit Flaschenhals-Verfügbarkeit + aufgelöster Stückliste an.
     *
     * @param list<array<string, mixed>> $materials
     * @return list<array<string, mixed>>
     */
    private function enrichVirtualComboAvailability(
        array $materials,
        ?\DateTime $startDate,
        ?\DateTime $endDate,
        string $excludeActivityId,
    ): array {
        $comboIds = [];
        foreach ($materials as $row) {
            if (($row['materialType'] ?? '') === 'virtual_combo') {
                $comboIds[] = (string) $row['materialItemId'];
            }
        }
        if ($comboIds === []) {
            return $materials;
        }

        // Basis-Konfiguration (Pflichtteile + default-Toggles) je Kombo auflösen.
        $resolvedByCombo = [];
        $allComponentIds = [];
        foreach ($comboIds as $cid) {
            $resolved = $this->comboResolution->resolve($cid, $this->comboResolution->defaultSelectedOptionIds($cid));
            $resolvedByCombo[$cid] = $resolved;
            foreach ($resolved['stock'] as $mid => $_part) {
                $allComponentIds[(string) $mid] = true;
            }
        }
        $componentAvail = $this->availabilityForIds(array_keys($allComponentIds), $startDate, $endDate, $excludeActivityId);

        return array_map(function (array $row) use ($resolvedByCombo, $componentAvail) {
            $cid = (string) $row['materialItemId'];
            if (!isset($resolvedByCombo[$cid])) {
                return $row;
            }
            $stock = $resolvedByCombo[$cid]['stock'];
            $bottleneck = null;
            $components = [];
            foreach ($stock as $mid => $part) {
                $qtyPer = (int) $part['qty_per_combo'];
                if ($qtyPer <= 0) {
                    continue;
                }
                $compAvail = (int) ($componentAvail[(string) $mid] ?? 0);
                $possible = intdiv($compAvail, $qtyPer);
                $bottleneck = $bottleneck === null ? $possible : min($bottleneck, $possible);
                $components[] = [
                    'materialItemId' => (string) $mid,
                    'name' => $part['name'],
                    'qtyPerCombo' => $qtyPer,
                    'availableForPeriod' => $compAvail,
                ];
            }
            $row['availableForPeriod'] = $bottleneck ?? 0;
            $row['comboBottleneck'] = $bottleneck ?? 0;
            $row['comboStockComponents'] = $components;
            return $row;
        }, $materials);
    }

    /**
     * Verfügbarkeit (available_for_period) je Material-Id – ohne Scope/Suche/Limit.
     *
     * @param list<string> $ids
     * @return array<string, int>
     */
    private function availabilityForIds(
        array $ids,
        ?\DateTime $startDate,
        ?\DateTime $endDate,
        string $excludeActivityId,
    ): array {
        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => (string) $v !== '')));
        if ($ids === []) {
            return [];
        }

        $idPh = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'avc_id' . $i;
            $idPh[] = ':' . $k;
            $params[$k] = $id;
        }
        $idIn = implode(', ', $idPh);

        $reservedExcludeSql = $excludeActivityId !== '' ? ' AND a.id != :exclude_activity_id' : '';
        $hasPeriod = $startDate !== null && $endDate !== null;

        $sql = "SELECT mi.id AS material_item_id,
                GREATEST(0,
                    CASE WHEN mi.material_type = 'physical_combo' THEN
                        COALESCE(batch_totals.total_qty, 0) - COALESCE(reserved.reserved_qty, 0)
                    ELSE
                        COALESCE(batch_totals.total_qty, 0) - COALESCE(stock_in_phys_combo.qty_in_phys_combo, 0) - COALESCE(reserved.reserved_qty, 0)
                    END
                )::INT AS available_for_period
                FROM material_item mi
                LEFT JOIN (
                    SELECT material_item_id AS mid, SUM(qty) AS total_qty
                    FROM material_batch WHERE status = 'active' GROUP BY material_item_id
                ) batch_totals ON batch_totals.mid = mi.id
                LEFT JOIN (
                    SELECT material_item_id AS mid, SUM(qty) AS qty_in_repair
                    FROM material_batch WHERE status = 'repair' GROUP BY material_item_id
                ) repair_totals ON repair_totals.mid = mi.id
                LEFT JOIN (
                    SELECT b.material_item_id AS mid, SUM(a.qty) AS qty_in_phys_combo
                    FROM batch_storage_allocation a
                    INNER JOIN material_batch b ON a.batch_id = b.id AND b.status = 'active'
                    INNER JOIN material_item combo_kiste ON combo_kiste.linked_container_batch_id = a.container_batch_id
                        AND combo_kiste.material_type = 'physical_combo' AND combo_kiste.deleted_at IS NULL
                    GROUP BY b.material_item_id
                ) stock_in_phys_combo ON stock_in_phys_combo.mid = mi.id
                " . MaterialAvailabilityReservationQuery::lateralReservedQtySql($hasPeriod, $reservedExcludeSql) . "
                WHERE mi.deleted_at IS NULL AND mi.id IN ($idIn)";

        if ($hasPeriod) {
            $params['start_date'] = $startDate->format('Y-m-d H:i:s');
            $params['end_date'] = $endDate->format('Y-m-d H:i:s');
        }
        if ($excludeActivityId !== '') {
            $params['exclude_activity_id'] = $excludeActivityId;
        }

        $rows = $this->connection->prepare($sql)->executeQuery($params)->fetchAllAssociative();
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['material_item_id']] = (int) $r['available_for_period'];
        }
        return $map;
    }

    /**
     * Phys.-Kombo-Stückliste: Komponenten mit Zugehörigkeit zu Sets (für «Teil von …» in der Suche).
     *
     * @param list<array<string, mixed>> $materials
     * @return list<array<string, mixed>>
     */
    private function enrichPhysicalComboComponentMembership(array $materials): array
    {
        $componentIds = [];
        foreach ($materials as $row) {
            $type = (string) ($row['materialType'] ?? '');
            if ($type === 'physical_combo' || $type === 'virtual_combo') {
                continue;
            }
            $componentIds[] = (string) $row['materialItemId'];
        }
        $componentIds = array_values(array_unique(array_filter($componentIds)));
        if ($componentIds === []) {
            return $materials;
        }

        $ph = [];
        $params = [];
        foreach ($componentIds as $i => $cid) {
            $k = 'pcc_mid' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $cid;
        }
        $inSql = implode(', ', $ph);
        $sql = "SELECT cc.component_material_id AS component_id,
                    combo.id AS combo_id,
                    combo.name AS combo_name,
                    COALESCE(comp.is_container, FALSE) AS is_container
                FROM material_combo_component cc
                INNER JOIN material_item combo ON combo.id = cc.parent_material_id
                    AND combo.material_type = 'physical_combo'
                    AND combo.deleted_at IS NULL
                    AND combo.combo_status <> 'draft'
                INNER JOIN material_item comp ON comp.id = cc.component_material_id
                    AND comp.deleted_at IS NULL
                WHERE cc.component_source = 'stock'
                  AND cc.component_material_id IN ($inSql)
                ORDER BY combo.name ASC";
        $rows = $this->connection->prepare($sql)->executeQuery($params)->fetchAllAssociative();

        /** @var array<string, list<array{comboId: string, comboName: string, isContainer: bool}>> $map */
        $map = [];
        foreach ($rows as $r) {
            $cid = (string) $r['component_id'];
            $map[$cid][] = [
                'comboId' => (string) $r['combo_id'],
                'comboName' => (string) $r['combo_name'],
                'isContainer' => filter_var($r['is_container'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return array_map(static function (array $row) use ($map): array {
            $mid = (string) ($row['materialItemId'] ?? '');
            $memberships = $map[$mid] ?? [];
            if ($memberships !== []) {
                $row['partOfPhysicalCombos'] = $memberships;
            }
            return $row;
        }, $materials);
    }

    /**
     * Phys.-Kombo: wie viele Set-Einheiten (eigene Batches) in der Referenz-Kiste liegen.
     *
     * @param list<array<string, mixed>> $materials
     * @return list<array<string, mixed>>
     */
    private function enrichPhysicalComboOwnCrateCounts(array $materials): array
    {
        $comboIds = [];
        foreach ($materials as $row) {
            if (($row['materialType'] ?? '') === 'physical_combo') {
                $comboIds[] = (string) $row['materialItemId'];
            }
        }
        $comboIds = array_values(array_unique(array_filter($comboIds)));
        if ($comboIds === []) {
            return $materials;
        }

        $ph = [];
        $params = [];
        foreach ($comboIds as $i => $cid) {
            $k = 'own_crate_' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $cid;
        }
        $inSql = implode(', ', $ph);
        $sql = "SELECT combo.id AS combo_id, COALESCE(SUM(a.qty), 0)::INT AS sets_in_own_crate
                FROM material_item combo
                LEFT JOIN batch_storage_allocation a
                    ON a.container_batch_id = combo.linked_container_batch_id
                LEFT JOIN material_batch b
                    ON b.id = a.batch_id
                   AND b.status = 'active'
                   AND b.material_item_id = combo.id
                WHERE combo.id IN ($inSql)
                  AND combo.material_type = 'physical_combo'
                  AND combo.linked_container_batch_id IS NOT NULL
                  AND combo.deleted_at IS NULL
                GROUP BY combo.id";
        $rows = $this->connection->prepare($sql)->executeQuery($params)->fetchAllAssociative();
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['combo_id']] = (int) $r['sets_in_own_crate'];
        }

        return array_map(static function (array $row) use ($map): array {
            if (($row['materialType'] ?? '') !== 'physical_combo') {
                return $row;
            }
            $cid = (string) ($row['materialItemId'] ?? '');
            $row['physicalComboSetsInOwnCrate'] = $map[$cid] ?? 0;
            return $row;
        }, $materials);
    }

    /**
     * Verfügbarkeit an Bestandslogik angleichen.
     *
     * Reparatur = zwei Wege (wie Material-Bestandsansicht):
     * - Charge mit Batch-Status «repair» (zählt nicht in totalStock, nur Anzeige)
     * - Offenes Werkstatt-Ticket bei noch «active» Charge → von frei abziehen
     *
     * @param list<array<string, mixed>> $materials
     * @return list<array<string, mixed>>
     */
    private function finalizeAvailabilityForPeriod(
        array $materials,
        string $departmentId,
        ?\DateTime $startDate,
        ?\DateTime $endDate,
        string $excludeActivityId,
    ): array {
        if ($materials === []) {
            return $materials;
        }

        $materialIds = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['materialItemId'] ?? ''),
            $materials,
        )));
        $materialIds = array_values(array_filter($materialIds));

        $workshopRepair = $this->workshopRepairQtyByMaterialIds($materialIds);
        $issuedAtEvent = $this->issuedAtEventQtyByMaterialIds(
            $departmentId,
            $materialIds,
            $startDate,
            $endDate,
            $excludeActivityId,
        );

        foreach ($materials as &$row) {
            $mid = (string) ($row['materialItemId'] ?? '');
            $type = (string) ($row['materialType'] ?? '');
            $total = (int) ($row['totalStock'] ?? 0);
            $repairBatchQty = (int) ($row['stockInRepair'] ?? 0);
            $workshopQty = (int) ($workshopRepair[$mid] ?? 0);
            $row['stockInRepair'] = $repairBatchQty + $workshopQty;
            $row['stockInRepairFromWorkshop'] = $workshopQty;
            /** Nur Werkstatt-Menge abziehen — Reparatur-Chargen sind nicht in totalStock. */
            $subtractRepair = $workshopQty;

            $reserved = (int) ($row['reservedInActivities'] ?? 0);
            $issued = (int) ($issuedAtEvent[$mid] ?? 0);
            $row['stockIssuedOut'] = $issued;

            if ($type === 'virtual_combo') {
                continue;
            }

            $lockQty = max($reserved, $issued);
            if ($type === 'physical_combo') {
                $row['availableForPeriod'] = max(0, $total - $subtractRepair - $lockQty);
                continue;
            }

            $physKisten = (int) ($row['stockInPhysComboKisten'] ?? 0);
            $row['availableForPeriod'] = max(0, $total - $subtractRepair - $physKisten - $lockQty);
        }
        unset($row);

        return $materials;
    }

    /**
     * @param list<string> $materialIds
     * @return array<string, int>
     */
    private function workshopRepairQtyByMaterialIds(array $materialIds): array
    {
        if ($materialIds === []) {
            return [];
        }

        $tickets = $this->entityManager->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.issueReport', 'ir')
            ->addSelect('ir')
            ->where('t.materialItemId IN (:mids)')
            ->andWhere('t.type = :type')
            ->andWhere('t.status NOT IN (:done)')
            ->setParameter('mids', $materialIds)
            ->setParameter('type', WorkshopTicket::TYPE_REPAIR)
            ->setParameter('done', [WorkshopTicket::STATUS_COMPLETED, WorkshopTicket::STATUS_CANCELLED])
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($tickets as $ticket) {
            if (!$ticket instanceof WorkshopTicket) {
                continue;
            }
            $mid = (string) $ticket->getMaterialItemId();
            if ($mid === '') {
                continue;
            }
            $report = $ticket->getIssueReport();
            $qty = $report ? max(1, $report->getQuantity()) : 1;
            $map[$mid] = ($map[$mid] ?? 0) + $qty;
        }

        return $map;
    }

    /**
     * Material «Am Event» (activity_item), optional nur bei Zeitraum-Overlap.
     *
     * @param list<string> $materialIds
     * @return array<string, int>
     */
    private function issuedAtEventQtyByMaterialIds(
        string $departmentId,
        array $materialIds,
        ?\DateTime $startDate,
        ?\DateTime $endDate,
        string $excludeActivityId,
    ): array {
        if ($materialIds === []) {
            return [];
        }

        $ph = [];
        $params = ['department_id' => $departmentId];
        foreach ($materialIds as $i => $mid) {
            $k = 'issued_mid_' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $mid;
        }
        $inSql = implode(', ', $ph);

        $periodSql = '';
        if ($startDate !== null && $endDate !== null) {
            $periodSql = 'AND (COALESCE(a.planning_start, a.usage_start) < :end_date)
                          AND (COALESCE(a.planning_end, a.usage_end) > :start_date)';
            $params['start_date'] = $startDate->format('Y-m-d H:i:s');
            $params['end_date'] = $endDate->format('Y-m-d H:i:s');
        }

        $excludeSql = $excludeActivityId !== '' ? 'AND a.id != :exclude_activity_id' : '';
        if ($excludeActivityId !== '') {
            $params['exclude_activity_id'] = $excludeActivityId;
        }

        $sql = "SELECT ai.material_item_id AS mid, COALESCE(SUM(ai.quantity), 0)::INT AS issued
                FROM activity_item ai
                INNER JOIN activity a ON a.id = ai.activity_id
                WHERE a.department_id = :department_id
                  AND a.deleted_at IS NULL
                  AND a.status = 'at_event'
                  AND ai.material_item_id IN ($inSql)
                  {$periodSql}
                  {$excludeSql}
                GROUP BY ai.material_item_id";

        $rows = $this->connection->prepare($sql)->executeQuery($params)->fetchAllAssociative();
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['mid']] = (int) $r['issued'];
        }

        return $map;
    }

    /**
     * Behälter-Zeile der physischen Kombo für Anzeige (Sack/Kiste …), wenn kein linked_container_batch gesetzt ist.
     *
     * @param list<array<string, mixed>> $candidates
     * @return array<string, mixed>|null
     */
    private function pickComboShellComponentForDisplay(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $score = static function (array $row): int {
            $name = mb_strtolower(trim((string) ($row['component_name'] ?? '')));
            $s = !empty($row['is_container']) ? 1000 : 0;
            if (preg_match('/\b(tasche|transport|sack|kiste|karton|fass|behälter|behaelter)\b/u', $name)) {
                $s += 100;
            }
            if (trim((string) ($row['component_pack_unit'] ?? '')) !== '') {
                $s += 10;
            }

            return $s;
        };

        usort($candidates, static fn (array $a, array $b): int => $score($b) <=> $score($a));

        return $candidates[0];
    }
}
