import type { ActivityIssueReportRow, ActivityItemRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  consumedQtyForMaterial,
  lossQtyForMaterial,
  notTakenToEventQtyForMaterial,
  repairQtyForMaterial,
} from '@/components/activities/packNotTakenHelpers'
import {
  isNonActionableContainerLine,
  shellPackItemForContainer,
} from '@/components/activities/packShellCrateHelpers'
import { isConsumablePackItem } from '@/utils/packItemConsumable'

export type MaterialJourneyReturnSummaryRow = {
  /** Stabiler Key für Tabelle (material oder container+material). */
  id: string
  materialItemId: string
  name: string
  categoryName: string | null
  isConsumable: boolean
  issued: number
  returned: number
  loss: number
  repair: number
  consumption: number
  /** Eingelagert (Pipeline). */
  stored: number
  /** Nachkauf / Nachlieferung (Activity-Items). */
  replenished: number
  /** 0 = lose / Kiste, 1 = Inhalt unter Kiste */
  depth: 0 | 1
  kind: 'loose' | 'crate' | 'in_crate' | 'replenishment'
  containerId?: string
}

export type MaterialJourneySummaryMode = 'return' | 'archive'

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

/** Nachkauf-Mengen pro Material aus Activity-Items. */
export function buildReplenishmentQtyByMaterial(
  items: ActivityItemRow[],
): Map<string, number> {
  const map = new Map<string, number>()
  for (const r of items) {
    if (r.is_replenishment !== true) continue
    const mid = (r.material_item_id ?? '').trim()
    if (!mid) continue
    map.set(mid, (map.get(mid) ?? 0) + Math.max(0, r.quantity))
  }
  return map
}

function replenishedFor(
  materialItemId: string,
  replenishmentByMaterial?: ReadonlyMap<string, number>,
): number {
  return Math.max(0, replenishmentByMaterial?.get(materialItemId) ?? 0)
}

function summaryRowFromPackItem(
  pi: ActivityPackItem,
  issues: ActivityIssueReportRow[],
  consumableMaterialItemIds: ReadonlySet<string> | undefined,
  extras: Pick<MaterialJourneyReturnSummaryRow, 'id' | 'depth' | 'kind' | 'containerId'>,
  replenishmentByMaterial?: ReadonlyMap<string, number>,
): MaterialJourneyReturnSummaryRow {
  const issued = pi.quantityIssued ?? 0
  const loss = lossQtyForMaterial(pi.materialItemId, issues)
  const repair = repairQtyForMaterial(pi.materialItemId, issues)
  const consumption = consumedQtyForMaterial(pi.materialItemId, issues)
  const notTaken = notTakenToEventQtyForMaterial(pi.materialItemId, issues)
  return {
    ...extras,
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
    stored: Math.max(0, pi.quantityStored ?? 0),
    replenished: replenishedFor(pi.materialItemId, replenishmentByMaterial),
  }
}

function hasReturnRelevantContainerLine(ci: ActivityPackContainerItem): boolean {
  if (isNonActionableContainerLine(ci)) return false
  return (
    (ci.quantity_issued ?? 0) > 0 ||
    (ci.quantity_returned ?? 0) > 0 ||
    (ci.quantity_packed ?? 0) > 0 ||
    (ci.quantity_stored ?? 0) > 0
  )
}

function packItemRelevantForMode(
  pi: ActivityPackItem,
  mode: MaterialJourneySummaryMode,
  replenishmentByMaterial?: ReadonlyMap<string, number>,
): boolean {
  if ((pi.quantityIssued ?? 0) > 0) return true
  if (mode === 'return') return false
  return (
    (pi.quantityStored ?? 0) > 0 ||
    (pi.quantityPacked ?? 0) > 0 ||
    (pi.quantityReturned ?? 0) > 0 ||
    replenishedFor(pi.materialItemId, replenishmentByMaterial) > 0
  )
}

/**
 * Retour- / Abschluss-Übersicht: Packkisten mit Inhalt eingerückt darunter, übriges Material lose.
 * Mode `archive` ergänzt Eingelagert + Nachkauf (auch ohne Ausgabe).
 */
export function buildMaterialJourneyReturnSummaryRows(
  packItems: ActivityPackItem[],
  issues: ActivityIssueReportRow[],
  consumableMaterialItemIds?: ReadonlySet<string>,
  packContainers: ActivityPackContainer[] = [],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]> = {},
  options?: {
    mode?: MaterialJourneySummaryMode
    replenishmentByMaterial?: ReadonlyMap<string, number>
  },
): MaterialJourneyReturnSummaryRow[] {
  const mode = options?.mode ?? 'return'
  const replenishmentByMaterial = options?.replenishmentByMaterial
  const rows: MaterialJourneyReturnSummaryRow[] = []
  const nestedMaterialIds = new Set<string>()
  const nestedShellIds = new Set<string>()
  const seenMaterialIds = new Set<string>()

  const containers = [...packContainers].sort((a, b) =>
    (a.label || a.id).localeCompare(b.label || b.id, undefined, { sensitivity: 'base' }),
  )

  for (const container of containers) {
    const lines = (containerItemsByContainerId[container.id] ?? []).filter(hasReturnRelevantContainerLine)
    const shell = shellPackItemForContainer(container, packItems)
    const shellIssued = shell?.quantityIssued ?? 0
    const shellStored = shell?.quantityStored ?? 0
    if (lines.length < 1 && shellIssued < 1 && (mode === 'return' || shellStored < 1)) continue

    const crateName =
      (container.label || '').trim() ||
      shell?.materialName ||
      container.container_material_item_id ||
      container.id

    if (shell && (shellIssued > 0 || (mode === 'archive' && shellStored > 0))) {
      nestedShellIds.add(shell.materialItemId)
      seenMaterialIds.add(shell.materialItemId)
      rows.push(
        summaryRowFromPackItem(
          shell,
          issues,
          consumableMaterialItemIds,
          {
            id: `crate:${container.id}`,
            depth: 0,
            kind: 'crate',
            containerId: container.id,
          },
          replenishmentByMaterial,
        ),
      )
      const last = rows[rows.length - 1]
      if (last && (container.label || '').trim()) {
        last.name = (container.label || '').trim()
      }
    } else {
      rows.push({
        id: `crate:${container.id}`,
        materialItemId: shell?.materialItemId ?? container.id,
        name: crateName,
        categoryName: shell?.categoryName ?? null,
        isConsumable: false,
        issued: 0,
        returned: 0,
        loss: 0,
        repair: 0,
        consumption: 0,
        stored: Math.max(0, shellStored),
        replenished: shell ? replenishedFor(shell.materialItemId, replenishmentByMaterial) : 0,
        depth: 0,
        kind: 'crate',
        containerId: container.id,
      })
      if (shell?.materialItemId) seenMaterialIds.add(shell.materialItemId)
    }

    for (const ci of lines) {
      const mid = (ci.material_item_id ?? '').trim()
      if (!mid) continue
      if (shell && mid === shell.materialItemId) continue
      nestedMaterialIds.add(mid)
      seenMaterialIds.add(mid)
      const pi = packItems.find((p) => p.materialItemId === mid)
      const issued = Math.max(ci.quantity_issued ?? 0, 0)
      const loss = lossQtyForMaterial(mid, issues)
      const repair = repairQtyForMaterial(mid, issues)
      const consumption = consumedQtyForMaterial(mid, issues)
      const notTaken = notTakenToEventQtyForMaterial(mid, issues)
      const pipelineReturned = ci.quantity_returned ?? pi?.quantityReturned ?? 0
      const issuedBase = issued > 0 ? issued : Math.max(ci.quantity_packed ?? 0, 0)
      rows.push({
        id: `in-crate:${container.id}:${ci.id}`,
        materialItemId: mid,
        name: ci.material_name ?? pi?.materialName ?? mid,
        categoryName: pi?.categoryName ?? null,
        isConsumable: pi
          ? isConsumablePackItem(pi, consumableMaterialItemIds)
          : Boolean(consumableMaterialItemIds?.has(mid)),
        issued: issuedBase,
        returned: displayReturnQty(
          issuedBase,
          pipelineReturned,
          loss,
          repair,
          consumption,
          notTaken,
        ),
        loss,
        repair,
        consumption,
        stored: Math.max(0, ci.quantity_stored ?? pi?.quantityStored ?? 0),
        replenished: replenishedFor(mid, replenishmentByMaterial),
        depth: 1,
        kind: 'in_crate',
        containerId: container.id,
      })
    }
  }

  const loose = packItems
    .filter((pi) => packItemRelevantForMode(pi, mode, replenishmentByMaterial))
    .filter((pi) => !nestedShellIds.has(pi.materialItemId))
    .filter((pi) => !nestedMaterialIds.has(pi.materialItemId))
    .map((pi) => {
      seenMaterialIds.add(pi.materialItemId)
      return summaryRowFromPackItem(
        pi,
        issues,
        consumableMaterialItemIds,
        {
          id: `loose:${pi.materialItemId}`,
          depth: 0,
          kind: 'loose',
        },
        replenishmentByMaterial,
      )
    })
    .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))

  const out = [...rows, ...loose]

  if (mode === 'archive' && replenishmentByMaterial) {
    const orphans: MaterialJourneyReturnSummaryRow[] = []
    for (const [mid, qty] of replenishmentByMaterial) {
      if (qty <= 0 || seenMaterialIds.has(mid)) continue
      const pi = packItems.find((p) => p.materialItemId === mid)
      orphans.push({
        id: `replenishment:${mid}`,
        materialItemId: mid,
        name: pi?.materialName ?? mid,
        categoryName: pi?.categoryName ?? null,
        isConsumable: true,
        issued: 0,
        returned: 0,
        loss: 0,
        repair: 0,
        consumption: 0,
        stored: Math.max(0, pi?.quantityStored ?? 0),
        replenished: qty,
        depth: 0,
        kind: 'replenishment',
      })
    }
    orphans.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
    out.push(...orphans)
  }

  return out
}
