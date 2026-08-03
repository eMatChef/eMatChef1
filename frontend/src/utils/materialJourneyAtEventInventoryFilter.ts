import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isPhysicalComboPackItem, packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'
import type { MaterialScanResolveResult } from '@/composables/materialScanResolve'

export function sortMaterialJourneyAtEventInventoryTasks(
  rows: MaterialJourneyTaskRow[],
): MaterialJourneyTaskRow[] {
  return [...rows].sort((a, b) => {
    const kindOrder = (kind: MaterialJourneyTaskRow['kind']) =>
      isMaterialJourneyCrateKind(kind) ? 0 : kind === 'combo' ? 1 : 2
    const kindDiff = kindOrder(a.kind) - kindOrder(b.kind)
    if (kindDiff !== 0) return kindDiff
    return a.title.localeCompare(b.title, undefined, { sensitivity: 'base' })
  })
}

function rowContainsMaterialId(
  row: MaterialJourneyTaskRow,
  materialItemId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): boolean {
  if (row.packItem?.materialItemId === materialItemId) return true
  if (!isMaterialJourneyCrateKind(row.kind) || !row.container) return false
  const items = containerItemsByContainerId[row.container.id] ?? []
  return items.some(
    (item) =>
      item.material_item_id === materialItemId && (item.quantity_issued ?? 0) > 0,
  )
}

export function materialJourneyAtEventRowsForMaterialId(
  materialItemId: string,
  rows: MaterialJourneyTaskRow[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): MaterialJourneyTaskRow[] {
  if (!materialItemId) return []
  return rows.filter((row) =>
    rowContainsMaterialId(row, materialItemId, containerItemsByContainerId),
  )
}

export function filterMaterialJourneyAtEventInventoryByText(
  rows: MaterialJourneyTaskRow[],
  rawQuery: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): MaterialJourneyTaskRow[] {
  const q = rawQuery.trim().toLowerCase()
  if (q.length < 2) return rows

  return rows.filter((row) => {
    if (row.title.toLowerCase().includes(q)) return true
    if (row.subtitle?.toLowerCase().includes(q)) return true
    if (row.categoryName?.toLowerCase().includes(q)) return true
    if (row.shelfLabel?.toLowerCase().includes(q)) return true
    if (row.packItem && packMaterialDisplayName(row.packItem).toLowerCase().includes(q)) {
      return true
    }
    if (isMaterialJourneyCrateKind(row.kind) && row.container) {
      const items = containerItemsByContainerId[row.container.id] ?? []
      if (
        items.some(
          (item) =>
            (item.quantity_issued ?? 0) > 0 &&
            (item.material_name ?? '').toLowerCase().includes(q),
        )
      ) {
        return true
      }
    }
    return false
  })
}

export function resolveMaterialJourneyAtEventInventoryRowIds(
  result: MaterialScanResolveResult | null,
  rows: MaterialJourneyTaskRow[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  materialItemId?: string | null,
): string[] {
  const matched = new Set<string>()

  if (result?.container) {
    const crate = rows.find(
      (row) => isMaterialJourneyCrateKind(row.kind) && row.container?.id === result.container?.id,
    )
    if (crate) matched.add(crate.id)
  }

  if (result?.packItem) {
    const pi = result.packItem
    if (isPhysicalComboPackItem(pi)) {
      const combo = rows.find((row) => row.kind === 'combo' && row.packItem?.id === pi.id)
      if (combo) matched.add(combo.id)
    } else {
      const loose = rows.find((row) => row.kind === 'loose' && row.packItem?.id === pi.id)
      if (loose) {
        matched.add(loose.id)
      } else {
        for (const row of materialJourneyAtEventRowsForMaterialId(
          pi.materialItemId,
          rows,
          containerItemsByContainerId,
        )) {
          matched.add(row.id)
        }
      }
    }
  }

  const mid = (materialItemId ?? result?.packItem?.materialItemId ?? '').trim()
  if (mid && matched.size === 0) {
    for (const row of materialJourneyAtEventRowsForMaterialId(
      mid,
      rows,
      containerItemsByContainerId,
    )) {
      matched.add(row.id)
    }
  }

  if (result?.type === 'text_match' && matched.size === 0 && result.title) {
    return filterMaterialJourneyAtEventInventoryByText(
      rows,
      result.title,
      containerItemsByContainerId,
    ).map((row) => row.id)
  }

  return [...matched]
}

export function materialJourneyAtEventRowIdsFromPackItems(
  packItems: ActivityPackItem[],
  rows: MaterialJourneyTaskRow[],
): string[] {
  const ids = new Set<string>()
  for (const pi of packItems) {
    if (isPhysicalComboPackItem(pi)) {
      const combo = rows.find((row) => row.kind === 'combo' && row.packItem?.id === pi.id)
      if (combo) ids.add(combo.id)
    } else {
      const loose = rows.find((row) => row.kind === 'loose' && row.packItem?.id === pi.id)
      if (loose) ids.add(loose.id)
    }
  }
  return [...ids]
}
