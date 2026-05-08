<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use App\Entity\Activity;
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
 * Berücksichtigt Gesamtbestand (Batches) minus reservierte Mengen (ActivityItems).
 */
class MaterialAvailabilityController extends AbstractController
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
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
     * - materialItemIds (optional, Komma-getrennte UUIDs; max. 50 — Ergebnis auf diese Material-Items begrenzen)
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
                if (preg_match('/^[0-9a-fA-F-]{36}$/', $id) === 1) {
                    $materialItemUuidList[] = $id;
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

            if ($hasPeriod) {
                // Zeitraum-basierte Verfügbarkeit (Department + globales J&S Material)
                $sql = "SELECT 
                        mi.id AS material_item_id,
                        mi.name,
                        mi.category_id,
                        mi.department_id AS source_department_id,
                        d.name AS source_department_name,
                        COALESCE(batch_totals.total_qty, 0)::INT AS total_stock,
                        COALESCE(reserved.reserved_qty, 0)::INT AS reserved_in_activities,
                        GREATEST(0, COALESCE(batch_totals.total_qty, 0) - COALESCE(reserved.reserved_qty, 0))::INT AS available_for_period
                        FROM material_item mi
                        JOIN department d ON d.id = mi.department_id
                        LEFT JOIN (
                            SELECT material_item_id AS mid, SUM(qty) AS total_qty
                            FROM material_batch
                            WHERE status = 'active'
                            GROUP BY material_item_id
                        ) batch_totals ON batch_totals.mid = mi.id
                        LEFT JOIN LATERAL (
                            SELECT COALESCE(SUM(ai.quantity), 0) AS reserved_qty
                            FROM activity_item ai
                            INNER JOIN activity a ON a.id = ai.activity_id
                            WHERE ai.material_item_id = mi.id
                              AND a.deleted_at IS NULL
                              AND a.status NOT IN ('cancelled', 'completed')
                              AND (
                                  (COALESCE(a.planning_start, a.usage_start) < :end_date)
                                  AND
                                  (COALESCE(a.planning_end, a.usage_end) > :start_date)
                              )
                        ) reserved ON TRUE
                        WHERE mi.deleted_at IS NULL
                          AND $scopeWhere $materialIdFilterSql";

                $params = array_merge([
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                ], $deptParams, $materialIdFilterParams);
            } else {
                // Ohne Zeitraum: Gesamtbestand (keine Reservierungsprüfung)
                $sql = "SELECT mi.id AS material_item_id, mi.name, mi.category_id, mi.department_id AS source_department_id, d.name AS source_department_name,
                        COALESCE(SUM(mb.quantity), 0) AS total_stock,
                        0 AS reserved_in_activities,
                        COALESCE(SUM(mb.quantity), 0) AS available_for_period
                        FROM material_item mi
                        JOIN department d ON d.id = mi.department_id
                        LEFT JOIN material_batch mb ON mb.material_item_id = mi.id AND mb.status = 'active'
                        WHERE mi.deleted_at IS NULL
                          AND $scopeWhere $materialIdFilterSql
                        GROUP BY mi.id, mi.name, mi.category_id, mi.department_id, d.name";

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
                $priceSql = "SELECT id, is_consumable, sale_price, rental_price_day, rental_price_week, rental_price_month, pack_size, pack_unit, is_js_material, external_source FROM material_item WHERE id IN ($placeholders)";
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

            // camelCase für Frontend
            $materials = array_map(function ($item) use ($priceMap) {
                $priceInfo = $priceMap[$item['material_item_id']] ?? [];
                return [
                    'materialItemId' => $item['material_item_id'],
                    'name' => $item['name'],
                    'categoryId' => $item['category_id'],
                    'sourceDepartmentId' => $item['source_department_id'] ?? null,
                    'sourceDepartmentName' => $item['source_department_name'] ?? null,
                    'totalStock' => (int) $item['total_stock'],
                    'reservedInActivities' => (int) $item['reserved_in_activities'],
                    'availableForPeriod' => (int) $item['available_for_period'],
                    'isConsumable' => (bool) ($priceInfo['is_consumable'] ?? false),
                    'salePrice' => $priceInfo['sale_price'] ?? null,
                    'rentalPriceDay' => $priceInfo['rental_price_day'] ?? null,
                    'rentalPriceWeek' => $priceInfo['rental_price_week'] ?? null,
                    'rentalPriceMonth' => $priceInfo['rental_price_month'] ?? null,
                    'packSize' => isset($priceInfo['pack_size']) ? (int) $priceInfo['pack_size'] : null,
                    'packUnit' => $priceInfo['pack_unit'] ?? null,
                    'isJsMaterial' => (bool) ($priceInfo['is_js_material'] ?? false),
                    'externalSource' => $priceInfo['external_source'] ?? null,
                ];
            }, $materials);

            return new JsonResponse($materials);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Datenbankfehler: ' . $e->getMessage()
            ], 500);
        }
    }
}
