import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  consumedQtyForMaterial,
  lossQtyForMaterial,
  notTakenToEventQtyForMaterial,
  repairQtyForMaterial,
} from '@/components/activities/packNotTakenHelpers'
import { isConsumablePackItem } from '@/utils/packItemConsumable'

export type MaterialJourneyReturnSummaryRow = {
  materialItemId: string
  name: string
  categoryName: string | null
  isConsumable: boolean
  issued: number
  returned: number
  loss: number
  repair: number
  consumption: number
}

/** Physisch retournierte Stück — Ausgegeben minus Verbrauch, Verlust, Reparatur, nicht mitgenommen. */
export function displayReturnQty(
  issued: number,
  pipelineReturned: number,
  loss: number,
  repair: number,
  consumption: number,
  notTaken: number,
): number {
  const fromPipeline = Math.max(0, pipelineReturned)
  const fromBalance = Math.max(0, issued - loss - repair - consumption - notTaken)
  if (fromPipeline > fromBalance) return fromBalance
  return fromPipeline > 0 ? fromPipeline : fromBalance
}

export function buildMaterialJourneyReturnSummaryRows(
  packItems: ActivityPackItem[],
  issues: ActivityIssueReportRow[],
  consumableMaterialItemIds?: ReadonlySet<string>,
): MaterialJourneyReturnSummaryRow[] {
  return packItems
    .filter((pi) => (pi.quantityIssued ?? 0) > 0)
    .map((pi) => {
      const issued = pi.quantityIssued
      const loss = lossQtyForMaterial(pi.materialItemId, issues)
      const repair = repairQtyForMaterial(pi.materialItemId, issues)
      const consumption = consumedQtyForMaterial(pi.materialItemId, issues)
      const notTaken = notTakenToEventQtyForMaterial(pi.materialItemId, issues)
      return {
        materialItemId: pi.materialItemId,
        name: pi.materialName,
        categoryName: pi.categoryName,
        isConsumable: isConsumablePackItem(pi, consumableMaterialItemIds),
        issued,
        returned: displayReturnQty(
          issued,
          pi.quantityReturned ?? 0,
          loss,
          repair,
          consumption,
          notTaken,
        ),
        loss,
        repair,
        consumption,
      }
    })
    .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
}
