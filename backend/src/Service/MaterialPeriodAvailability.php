<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Freie Menge je Material für einen Zeitraum (Bestand − Kombos − Reservierungen). Eine Rechnung für die Verfügbarkeits-API,
 * die Aktivitäten und die Gast-Zusagen des Grossanlasses (`MaterialAvailabilityReservationQuery`).
 */
final class MaterialPeriodAvailability
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $ids
     * @return array<string, int> material_item_id => freie Menge
     */
    public function availableForIds(
        array $ids,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        string $excludeActivityId = '',
        string $excludeGuestShareId = '',
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
                        COALESCE(batch_totals.total_qty, 0) - COALESCE(stock_in_phys_combo.qty_in_phys_combo, 0) - COALESCE(stock_as_linked_ref.qty_as_linked_ref, 0) - COALESCE(reserved.reserved_qty, 0)
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
                " . MaterialAvailabilityReservationQuery::stockAsLinkedRefContainerJoinSql() . "
                " . MaterialAvailabilityReservationQuery::lateralReservedQtySql($hasPeriod, $reservedExcludeSql, $excludeGuestShareId !== '' ? ' AND gs.id != :exclude_guest_share_id' : '') . "
                WHERE mi.deleted_at IS NULL AND mi.id IN ($idIn)";

        if ($hasPeriod) {
            $params['start_date'] = $startDate->format('Y-m-d H:i:s');
            $params['end_date'] = $endDate->format('Y-m-d H:i:s');
        }
        if ($excludeActivityId !== '') {
            $params['exclude_activity_id'] = $excludeActivityId;
        }
        if ($excludeGuestShareId !== '') {
            $params['exclude_guest_share_id'] = $excludeGuestShareId;
        }

        $rows = $this->connection->prepare($sql)->executeQuery($params)->fetchAllAssociative();
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['material_item_id']] = (int) $r['available_for_period'];
        }
        return $map;
    }
}
