<?php

namespace App\Service;

use App\Entity\Activity;

/**
 * SQL-Bausteine für Material-Reservierungen in der Verfügbarkeits-API.
 *
 * Zwei Ebenen (siehe docs/activities/material-pipeline.md):
 * 1. Bestell-Reservierung (draft…approved): activity_item.quantity, nur bei Zeitraum-Overlap
 * 2. Physische Sperre (packing…returned): GREATEST(packed, returned, issued) − stored.
 *    Bei Zeitraum-Abfrage nur wenn Planungszeitraum der blockierenden Aktivität überlappt
 *    (Material von früherem Event zählt für spätere Events nicht, sobald Planung nicht kollidiert).
 *    Ohne Zeitraum-Abfrage: alle offenen Pipeline-Mengen.
 *    Aktivitäts-Status «completed» beeinflusst die Verfügbarkeit nicht.
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

        $pipelinePeriodOverlapSql = $withPeriodOverlap
            ? $periodOverlapSql
            : '';

        $pipelineLockQty = self::pipelineLockQtyCaseSql('pi');

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
            {$pipelineLockQty} AS qty
        FROM activity_pack_item pi
        INNER JOIN activity a ON a.id = pi.activity_id
        WHERE pi.material_item_id = mi.id
          AND a.deleted_at IS NULL
          AND a.status IN ({$pipelineStatuses})
          AND (
              pi.quantity_packed > 0
              OR pi.quantity_returned > 0
              OR pi.quantity_issued > 0
              OR (a.status = 'packing' AND pi.quantity_ordered > 0)
          )
          AND ({$pipelineLockQty}) > 0
          {$pipelinePeriodOverlapSql}
          {$excludeActivitySql}
    ) part
) reserved ON TRUE
SQL;
    }

    /** Gepackte/retournierte/ausgegebene Menge minus eingelagert — frei sobald quantity_stored aufgeholt hat. */
    private static function pipelineLockQtyCaseSql(string $alias): string
    {
        return <<<SQL
CASE
    WHEN a.status = 'packing'
         AND {$alias}.quantity_packed = 0
         AND {$alias}.quantity_returned = 0
         AND {$alias}.quantity_ordered > 0
        THEN {$alias}.quantity_ordered
    ELSE GREATEST(
        GREATEST({$alias}.quantity_packed, {$alias}.quantity_returned),
        {$alias}.quantity_issued
    ) - COALESCE({$alias}.quantity_stored, 0)
END
SQL;
    }

    /** @param list<string> $values */
    private static function sqlInList(array $values): string
    {
        return implode(', ', array_map(static fn (string $v): string => "'" . $v . "'", $values));
    }
}
