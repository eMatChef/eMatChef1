import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { consumedQtyForMaterial } from '@/components/activities/packNotTakenHelpers'
import {
  computeLooseQtyStillAtEventForReturn,
  type PackQuantityContext,
} from '@/components/activities/packStageQuantityLayer'

export function consumableBookedConsumptionQty(
  pi: ActivityPackItem,
  issues: ActivityIssueReportRow[],
): number {
  if (!pi.isConsumable) return 0
  return consumedQtyForMaterial(pi.materialItemId, issues)
}

/** Gebucht (Pack-Zeile) — Nachlieferung folgt in späterer Iteration. */
export function consumableTotalBookedQty(pi: ActivityPackItem): number {
  return Math.max(0, pi.quantityOrdered ?? 0)
}

/** Verbrauch offen: gebucht − retourniert − verbraucht. */
export function consumableConsumptionRemaining(
  pi: ActivityPackItem,
  issues: ActivityIssueReportRow[],
): number {
  if (!pi.isConsumable) return 0
  const returned = pi.quantityReturned ?? 0
  const consumed = consumableBookedConsumptionQty(pi, issues)
  return Math.max(0, consumableTotalBookedQty(pi) - returned - consumed)
}

export function consumablePhysicalReturnMax(
  pi: ActivityPackItem,
  packQuantityCtx: PackQuantityContext,
  issues: ActivityIssueReportRow[],
): number {
  const atEvent = computeLooseQtyStillAtEventForReturn(pi, packQuantityCtx)
  if (!pi.isConsumable) return atEvent
  const accountingLeft = consumableConsumptionRemaining(pi, issues)
  return Math.min(atEvent, accountingLeft)
}

export function resolveConsumableReturnQty(
  pi: ActivityPackItem,
  packQuantityCtx: PackQuantityContext,
  issues: ActivityIssueReportRow[],
  moveQty: number,
): number {
  if (!pi.isConsumable) return moveQty
  return Math.min(moveQty, consumablePhysicalReturnMax(pi, packQuantityCtx, issues))
}

export function returnCrateConsumableState(
  materialItemId: string | null,
  packItems: ActivityPackItem[],
  issues: ActivityIssueReportRow[],
): { consumptionDone: boolean; consumptionOpen: number } {
  if (!materialItemId) return { consumptionDone: true, consumptionOpen: 0 }
  const pi = packItems.find((p) => p.materialItemId === materialItemId)
  if (!pi?.isConsumable) return { consumptionDone: true, consumptionOpen: 0 }
  const consumptionOpen = consumableConsumptionRemaining(pi, issues)
  return { consumptionDone: consumptionOpen <= 0, consumptionOpen }
}
