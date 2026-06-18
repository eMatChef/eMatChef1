import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'

export const MATERIAL_JOURNEY_NO_SHELF_KEY = '__none__'

export type MaterialJourneyRegalGroup = {
  key: string
  label: string
  rows: MaterialJourneyTaskRow[]
  openCount: number
}

/** Display label for Regal / Fach / Ort (storage_location). */
export function materialJourneyShelfLabel(pi: ActivityPackItem | null | undefined): string {
  if (!pi) return ''
  const rack = pi.storageRackName?.trim()
  const slot = pi.storageSlotName?.trim()
  const addr = pi.storageAddressName?.trim()
  if (rack && slot) return `${rack} · ${slot}`
  if (rack) return rack
  if (slot) return slot
  if (addr) return addr
  return ''
}

export function materialJourneyShelfKey(label: string): string {
  const trimmed = label.trim()
  return trimmed ? trimmed.toLowerCase() : MATERIAL_JOURNEY_NO_SHELF_KEY
}

export function materialJourneyShelfForContainer(
  container: ActivityPackContainer,
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined,
): { shelfLabel: string; shelfKey: string } {
  const shell = shellPackItemForContainer(container.id)
  const shelfLabel = materialJourneyShelfLabel(shell)
  return { shelfLabel, shelfKey: materialJourneyShelfKey(shelfLabel) }
}

export function sortMaterialJourneyTasksByShelf(
  rows: MaterialJourneyTaskRow[],
): MaterialJourneyTaskRow[] {
  return [...rows].sort((a, b) => {
    if (a.shelfKey === MATERIAL_JOURNEY_NO_SHELF_KEY && b.shelfKey !== MATERIAL_JOURNEY_NO_SHELF_KEY) {
      return 1
    }
    if (b.shelfKey === MATERIAL_JOURNEY_NO_SHELF_KEY && a.shelfKey !== MATERIAL_JOURNEY_NO_SHELF_KEY) {
      return -1
    }
    const shelfCmp = (a.shelfLabel ?? '').localeCompare(b.shelfLabel ?? '', undefined, {
      sensitivity: 'base',
    })
    if (shelfCmp !== 0) return shelfCmp
    return a.title.localeCompare(b.title, undefined, { sensitivity: 'base' })
  })
}

export function groupMaterialJourneyTasksByShelf(
  rows: MaterialJourneyTaskRow[],
  noShelfLabel: string,
): MaterialJourneyRegalGroup[] {
  const sorted = sortMaterialJourneyTasksByShelf(rows)
  const groups: MaterialJourneyRegalGroup[] = []
  let current: MaterialJourneyRegalGroup | null = null

  for (const row of sorted) {
    if (!current || current.key !== row.shelfKey) {
      current = {
        key: row.shelfKey,
        label:
          row.shelfKey === MATERIAL_JOURNEY_NO_SHELF_KEY
            ? noShelfLabel
            : row.shelfLabel ?? noShelfLabel,
        rows: [],
        openCount: 0,
      }
      groups.push(current)
    }
    current.rows.push(row)
    if (row.isOpen) current.openCount += 1
  }

  return groups
}
