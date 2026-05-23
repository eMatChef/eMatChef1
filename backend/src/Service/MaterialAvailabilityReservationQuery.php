<?php

namespace App\Service;

use App\Entity\Activity;

/**
 * SQL-Bausteine für Material-Reservierungen in der Verfügbarkeits-API.
 *
 * Zwei Ebenen (siehe docs/activities/material-pipeline.md):
 * 1. Bestell-Reservierung (draft…approved): activity_item.quantity, nur bei Zeitraum-Overlap
 * 2. Physische Sperre (packing…returned): gepackte / retournierte Menge — unabhängig vom Zeitraum
 */
final class MaterialAvailabilityReservationQuery
{
    /** @return list<string> */
    public static function orderReservationStatuses(): array
    {
        return [
            Activity::STATUS_DRAFT,
            Activity::STATUS_SUBMITTED,
            Activity::STATUS_APPROVED,
        ];
    }

    /** @return list<string> */
    public static function pipelineLockStatuses(): array
    {
        return [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_ISSUED,
            Activity::STATUS_RETURNED,
        ];
    }

    /**
     * LATERAL-Subquery: reserved_qty pro material_item.
     *
     * @param bool $withPeriodOverlap Bestell-Reservierung nur wenn Abfrage einen Zeitraum hat
     */
    public static function lateralReservedQtySql(bool $withPeriodOverlap, string $excludeActivitySql): string
    {
        $orderStatuses = self::sqlInList(self::orderReservationStatuses());
        $pipelineStatuses = self::sqlInList(self::pipelineLockStatuses());

        $periodOverlapSql = $withPeriodOverlap
            ? 'AND (COALESCE(a.planning_start, a.usage_start) < :end_date)
               AND (COALESCE(a.planning_end, a.usage_end) > :start_date)'
            : 'AND FALSE';

        return <<<SQL
LEFT JOIN LATERAL (
    SELECT COALESCE(SUM(part.qty), 0) AS reserved_qty
    FROM (
        SELECT ai.quantity AS qty
        FROM activity_item ai
        INNER JOIN activity a ON a.id = ai.activity_id
        WHERE ai.material_item_id = mi.id
          AND a.deleted_at IS NULL
          AND a.status IN ({$orderStatuses})
          {$periodOverlapSql}
          {$excludeActivitySql}

        UNION ALL

        SELECT
            CASE
                WHEN a.status = 'packing'
                     AND pi.quantity_packed = 0
                     AND pi.quantity_returned = 0
                     AND pi.quantity_ordered > 0
                    THEN pi.quantity_ordered
                ELSE GREATEST(pi.quantity_packed, pi.quantity_returned) - COALESCE(pi.quantity_stored, 0)
            END AS qty
        FROM activity_pack_item pi
        INNER JOIN activity a ON a.id = pi.activity_id
        WHERE pi.material_item_id = mi.id
          AND a.deleted_at IS NULL
          AND a.status IN ({$pipelineStatuses})
          AND (
              pi.quantity_packed > 0
              OR pi.quantity_returned > 0
              OR (a.status = 'packing' AND pi.quantity_ordered > 0)
          )
          AND (
              CASE
                  WHEN a.status = 'packing'
                       AND pi.quantity_packed = 0
                       AND pi.quantity_returned = 0
                       AND pi.quantity_ordered > 0
                      THEN pi.quantity_ordered
                  ELSE GREATEST(pi.quantity_packed, pi.quantity_returned) - COALESCE(pi.quantity_stored, 0)
              END
          ) > 0
          {$excludeActivitySql}
    ) part
) reserved ON TRUE
SQL;
    }

    /** @param list<string> $values */
    private static function sqlInList(array $values): string
    {
        return implode(', ', array_map(static fn (string $v): string => "'" . $v . "'", $values));
    }
}
