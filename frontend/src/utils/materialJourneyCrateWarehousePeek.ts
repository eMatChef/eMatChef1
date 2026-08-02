import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { getContainerBatches } from '@/api/storageLocations'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { peekSectionsForJourneyContainer } from '@/composables/useMaterialJourneyCrateSections'
import type { ComposerTranslation } from 'vue-i18n'

export type CrateWarehousePeekLine = {
  name: string
  qty: number
}

export type CrateWarehousePeekSummary = {
  lineCount: number
  totalQty: number
  lines: CrateWarehousePeekLine[]
}

/** Lager-Vorlage-Inhalt einer Kiste (ohne Packlisten-Buchungen). */
export function crateWarehouseTemplatePeekSummary(
  container: ActivityPackContainer,
  ctx: MaterialJourneyCratePeekMaps & {
    containerItemsByContainerId: Record<string, import('@/api/activityContainers').ActivityPackContainerItem[]>
  },
  shellPackItem: ActivityPackItem | null | undefined,
  t: ComposerTranslation,
  packItems: ActivityPackItem[] = [],
  packContainers: ActivityPackContainer[] = [],
): CrateWarehousePeekSummary | null {
  const sections = peekSectionsForJourneyContainer(
    container,
    ctx,
    shellPackItem,
    t,
    packItems,
    packContainers,
  )
  const fixed = sections.find((sec) => sec.subsectionKey === 'fixed')
  if (!fixed?.lines.length) return null

  const lines: CrateWarehousePeekLine[] = fixed.lines.map((line) => ({
    name: line.materialName,
    qty: line.quantity,
  }))
  const totalQty = lines.reduce((sum, row) => sum + row.qty, 0)
  if (totalQty <= 0) return null

  return { lineCount: lines.length, totalQty, lines }
}

/** Nur explizit leere Lager-Kisten dürfen als Packkiste genutzt werden. */
export async function isContainerBatchEmptyForPack(
  departmentId: string,
  activityId: string,
  batchId: string,
): Promise<boolean> {
  const id = batchId.trim()
  if (!id) return false
  const batches = await getContainerBatches(departmentId.trim(), { activityId })
  const batch = batches.find((row) => row.id === id)
  if (!batch) return false
  return batch.storage_empty === true
}
