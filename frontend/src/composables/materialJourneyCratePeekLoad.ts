import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { resolvePackContainerWarehouseBatchId } from '@/components/activities/packShellCrateHelpers'
import { getComboComponents, type ComboComponent } from '@/api/materials'
import { getContainerBatchContents } from '@/api/storageLocations'
import type { RackContentsItem } from '@/api/storageLocations'

export type MaterialJourneyCratePeekMaps = {
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>
  containerWarehouseContentsByContainerId: Record<string, RackContentsItem[]>
  comboComponentsByMaterialId: Record<string, ComboComponent[]>
}

export const emptyMaterialJourneyCratePeekMaps = (): MaterialJourneyCratePeekMaps => ({
  containerWarehouseTemplateByContainerId: {},
  containerWarehouseContentsByContainerId: {},
  comboComponentsByMaterialId: {},
})

export async function loadMaterialJourneyCratePeekData(
  packContainers: ActivityPackContainer[],
  packItems: ActivityPackItem[],
): Promise<MaterialJourneyCratePeekMaps> {
  const templateNext: Record<string, Set<string>> = {}
  const contentsNext: Record<string, RackContentsItem[]> = {}

  await Promise.all(
    packContainers.map(async (c) => {
      const batchId = resolvePackContainerWarehouseBatchId(c, packItems, packContainers)
      if (!batchId) return
      try {
        const data = await getContainerBatchContents(batchId)
        const mids = new Set<string>()
        const contents: RackContentsItem[] = []
        for (const row of data.contents ?? []) {
          const mid = (row.material_id ?? '').trim()
          if (mid) mids.add(mid)
          contents.push(row)
        }
        templateNext[c.id] = mids
        contentsNext[c.id] = contents
      } catch {
        /* Lager-Vorlage optional */
      }
    }),
  )

  const comboMids = new Set<string>()
  for (const pi of packItems) {
    if (pi.materialType === 'physical_combo') comboMids.add(pi.materialItemId)
  }
  for (const c of packContainers) {
    const mid = (c.container_material_item_id ?? '').trim()
    if (mid) {
      // Backend setzt Shell-Material auch ohne Pack-Zeile — BOM trotzdem laden
      comboMids.add(mid)
    }
    const bid = resolvePackContainerWarehouseBatchId(c, packItems, packContainers)
    if (bid) {
      const shell = packItems.find((p) => (p.linkedContainerBatchId ?? '').trim() === bid)
      if (shell?.materialType === 'physical_combo') comboMids.add(shell.materialItemId)
    }
  }

  const comboNext: Record<string, ComboComponent[]> = {}
  await Promise.all(
    [...comboMids].map(async (mid) => {
      try {
        comboNext[mid] = await getComboComponents(mid)
      } catch {
        comboNext[mid] = []
      }
    }),
  )

  return {
    containerWarehouseTemplateByContainerId: templateNext,
    containerWarehouseContentsByContainerId: contentsNext,
    comboComponentsByMaterialId: comboNext,
  }
}
