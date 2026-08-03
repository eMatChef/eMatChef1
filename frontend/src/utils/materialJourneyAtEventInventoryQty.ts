import type { ComposerTranslation } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  consumedQtyForMaterial,
  lossQtyForMaterial,
  notTakenToEventQtyForMaterial,
  repairQtyForMaterial,
} from '@/components/activities/packNotTakenHelpers'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'

export type AtEventQtySummary = {
  total: number
  taken: number
  loss: number
  repair: number
  consumption: number
  notTaken: number
}

export function emptyAtEventQtySummary(): AtEventQtySummary {
  return { total: 0, taken: 0, loss: 0, repair: 0, consumption: 0, notTaken: 0 }
}

export function atEventQtySummary(
  totalIssued: number,
  materialItemId: string | null | undefined,
  issues: ActivityIssueReportRow[],
): AtEventQtySummary {
  const total = Math.max(0, totalIssued)
  if (!materialItemId || total <= 0) {
    return { total, taken: total, loss: 0, repair: 0, consumption: 0, notTaken: 0 }
  }
  const loss = lossQtyForMaterial(materialItemId, issues)
  const repair = repairQtyForMaterial(materialItemId, issues)
  const consumption = consumedQtyForMaterial(materialItemId, issues)
  const notTaken = notTakenToEventQtyForMaterial(materialItemId, issues)
  const deviation = loss + repair + consumption + notTaken
  const taken = Math.max(0, total - deviation)
  return { total, taken, loss, repair, consumption, notTaken }
}

export function mergeAtEventQtySummaries(
  a: AtEventQtySummary,
  b: AtEventQtySummary,
): AtEventQtySummary {
  return {
    total: a.total + b.total,
    taken: a.taken + b.taken,
    loss: a.loss + b.loss,
    repair: a.repair + b.repair,
    consumption: a.consumption + b.consumption,
    notTaken: a.notTaken + b.notTaken,
  }
}

export function formatAtEventQtyLabel(
  summary: AtEventQtySummary,
  t: ComposerTranslation,
): string {
  const parts: string[] = [
    t('activities.materialJourney.row.atEventTotal', { count: summary.total }),
  ]
  const hasDeviation =
    summary.loss > 0 ||
    summary.repair > 0 ||
    summary.consumption > 0 ||
    summary.notTaken > 0
  if (!hasDeviation) return parts[0]
  if (summary.taken > 0) {
    parts.push(t('activities.materialJourney.row.atEventTaken', { count: summary.taken }))
  }
  if (summary.loss > 0) {
    parts.push(t('activities.materialJourney.row.atEventLoss', { count: summary.loss }))
  }
  if (summary.repair > 0) {
    parts.push(t('activities.materialJourney.row.atEventRepair', { count: summary.repair }))
  }
  if (summary.consumption > 0) {
    parts.push(t('activities.materialJourney.row.atEventConsumption', { count: summary.consumption }))
  }
  if (summary.notTaken > 0) {
    parts.push(t('activities.materialJourney.row.atEventNotTaken', { count: summary.notTaken }))
  }
  return parts.join(' · ')
}

/** Menge einer Kistenzeile für Anzeige / Meldungen (Pipeline-Felder). */
export function containerLineQtyAtEvent(item: ActivityPackContainerItem): number {
  return Math.max(
    item.quantity_packed ?? 0,
    item.quantity_transport_to ?? 0,
    item.quantity_issued ?? 0,
    item.quantity_transport_back ?? 0,
    item.quantity_returned ?? 0,
  )
}

export function crateShellIssuedAtEvent(
  row: MaterialJourneyTaskRow,
  shellPackItemForContainer?: (containerId: string) => ActivityPackItem | undefined,
): boolean {
  if (!isMaterialJourneyCrateKind(row.kind) || !row.container || !shellPackItemForContainer) return false
  const shell = shellPackItemForContainer(row.container.id)
  return (shell?.quantityIssued ?? 0) > 0
}

export function issuedQtyForAccordionLineAtEvent(
  line: MaterialJourneyAccordionLine,
  row: MaterialJourneyTaskRow,
  options?: { crateShellIssued?: boolean },
): number {
  const explicitIssued = line.issuedQty ?? 0
  if (explicitIssued > 0) return explicitIssued
  if (row.kind === 'combo' && row.packItem && (row.packItem.quantityIssued ?? 0) > 0) {
    return line.quantity
  }
  /** Kisteninhalt reist mit Shell — quantity_packed zählt als am Anlass. */
  if (options?.crateShellIssued && isMaterialJourneyCrateKind(row.kind) && line.quantity > 0) {
    return line.quantity
  }
  return line.quantity > 0 ? line.quantity : explicitIssued
}

export function atEventQtySummaryForCrateContainer(
  containerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  issues: ActivityIssueReportRow[],
  previewLines: MaterialJourneyAccordionLine[] = [],
  options?: { shellIssuedAtEvent?: boolean },
): AtEventQtySummary {
  let summary = emptyAtEventQtySummary()
  const items = containerItemsByContainerId[containerId] ?? []
  const crateRow = { kind: 'crate' as const, container: { id: containerId } } as MaterialJourneyTaskRow

  for (const item of items) {
    let issued = item.quantity_issued ?? 0
    if (issued <= 0 && options?.shellIssuedAtEvent) {
      issued = containerLineQtyAtEvent(item)
    }
    if (issued <= 0) continue
    summary = mergeAtEventQtySummaries(
      summary,
      atEventQtySummary(issued, item.material_item_id, issues),
    )
  }
  if (summary.total > 0) return summary

  for (const line of previewLines) {
    const issued = issuedQtyForAccordionLineAtEvent(line, crateRow, {
      crateShellIssued: options?.shellIssuedAtEvent,
    })
    if (issued <= 0 || !line.materialItemId) continue
    summary = mergeAtEventQtySummaries(
      summary,
      atEventQtySummary(issued, line.materialItemId, issues),
    )
  }
  return summary
}
