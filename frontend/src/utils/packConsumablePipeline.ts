import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { consumedQtyForMaterial } from '@/components/activities/packNotTakenHelpers'
import { computeConsumableQtyAlreadyBeyondCurrentStage } from '@/components/activities/packStageQuantityLayer'
import type { PackStage } from '@/components/activities/packStageQuantities'
import { getStageRightQty, isPackReturnStage, isPackUnpackStage } from '@/components/activities/packStageQuantities'

/** Gebuchte Verbrauchsmenge aus Meldungen. */
export function consumableBookedConsumptionQty(
  pi: ActivityPackItem,
  issues: ActivityIssueReportRow[],
): number {
  if (!pi.isConsumable) return 0
  return consumedQtyForMaterial(pi.materialItemId, issues)
}

/** Basis gebuchte Menge (Pack-Zeile; Nachlieferung über quantity_ordered abgedeckt). */
export function consumableTotalBookedQty(pi: ActivityPackItem): number {
  return Math.max(pi.quantityOrdered ?? 0, pi.quantityPacked ?? 0)
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
  issues: ActivityIssueReportRow[],
  looseQtyStillAtEvent: number,
): number {
  if (!pi.isConsumable) return looseQtyStillAtEvent
  const accountingLeft = Math.max(
    0,
    consumableTotalBookedQty(pi) -
      consumableBookedConsumptionQty(pi, issues) -
      (pi.quantityReturned ?? 0),
  )
  return Math.min(looseQtyStillAtEvent, accountingLeft)
}

export function consumableShowsZeroOnStageLeft(
  pi: ActivityPackItem,
  stage: PackStage,
  issues: ActivityIssueReportRow[],
  effectiveStageLeftQty: number,
  stageRightQty: number,
): boolean {
  if (!pi.isConsumable || consumableBookedConsumptionQty(pi, issues) <= 0) return false
  if (isPackReturnStage(stage) || isPackUnpackStage(stage)) return false
  if (effectiveStageLeftQty > 0) return false
  if (stageRightQty > 0) return false
  if (computeConsumableQtyAlreadyBeyondCurrentStage(pi, stage)) return false
  return true
}
