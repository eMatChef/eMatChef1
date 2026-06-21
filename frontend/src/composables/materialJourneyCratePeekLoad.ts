import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { resolvePackContainerWarehouseBatchId } from '@/components/activities/packShellCrateHelpers'
import { getComboComponents, getLinkedPhysicalComboForContainerBatch, type ComboComponent } from '@/api/materials'
import { getContainerBatchContents } from '@/api/storageLocations'
import type { RackContentsItem } from '@/api/storageLocations'

export type MaterialJourneyCratePeekMaps = {
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>
  containerWarehouseContentsByContainerId: Record<string, RackContentsItem[]>
  comboComponentsByMaterialId: Record<string, ComboComponent[]>
  /** Phys.-Kombi-Material-ID pro Pack-Behälter (aus Charge / Shell aufgelöst). */
  comboMaterialIdByContainerId: Record<string, string>
}

export const emptyMaterialJourneyCratePeekMaps = (): MaterialJourneyCratePeekMaps => ({
  containerWarehouseTemplateByContainerId: {},
  containerWarehouseContentsByContainerId: {},
  comboComponentsByMaterialId: {},
  comboMaterialIdByContainerId: {},
})

function physicalComboPackItemForMaterialId(
  materialId: string,
  packItems: ActivityPackItem[],
): ActivityPackItem | undefined {
  const mid = materialId.trim()
  if (!mid) return undefined
  return packItems.find((p) => p.materialItemId === mid && p.materialType === 'physical_combo')
}

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

  const linkedComboIdByBatchId: Record<string, string> = {}
  await Promise.all(
    packContainers.map(async (c) => {
      const batchId = resolvePackContainerWarehouseBatchId(c, packItems, packContainers)
      if (!batchId || linkedComboIdByBatchId[batchId]) return
      try {
        const linked = await getLinkedPhysicalComboForContainerBatch(batchId)
        if (linked?.id) linkedComboIdByBatchId[batchId] = linked.id
      } catch {
        /* optional */
      }
    }),
  )

  const comboMids = new Set<string>()
  for (const pi of packItems) {
    if (pi.materialType === 'physical_combo') comboMids.add(pi.materialItemId)
  }
  for (const c of packContainers) {
    const containerMid = (c.container_material_item_id ?? '').trim()
    if (physicalComboPackItemForMaterialId(containerMid, packItems)) {
      comboMids.add(containerMid)
    }
    const batchId = resolvePackContainerWarehouseBatchId(c, packItems, packContainers)
    if (batchId) {
      const shell = packItems.find((p) => (p.linkedContainerBatchId ?? '').trim() === batchId)
      if (shell?.materialType === 'physical_combo') comboMids.add(shell.materialItemId)
      const linkedId = linkedComboIdByBatchId[batchId]
      if (linkedId) comboMids.add(linkedId)
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

  const comboMaterialIdByContainerId: Record<string, string> = {}
  for (const c of packContainers) {
    const candidates: string[] = []
    const batchId = resolvePackContainerWarehouseBatchId(c, packItems, packContainers)
    if (batchId) {
      const shell = packItems.find((p) => (p.linkedContainerBatchId ?? '').trim() === batchId)
      if (shell?.materialType === 'physical_combo') candidates.push(shell.materialItemId)
      const linkedId = linkedComboIdByBatchId[batchId]
      if (linkedId) candidates.push(linkedId)
    }
    const containerMid = (c.container_material_item_id ?? '').trim()
    if (physicalComboPackItemForMaterialId(containerMid, packItems)) {
      candidates.push(containerMid)
    }
    for (const mid of candidates) {
      if ((comboNext[mid] ?? []).length > 0) {
        comboMaterialIdByContainerId[c.id] = mid
        break
      }
    }
  }

  return {
    containerWarehouseTemplateByContainerId: templateNext,
    containerWarehouseContentsByContainerId: contentsNext,
    comboComponentsByMaterialId: comboNext,
    comboMaterialIdByContainerId,
  }
}
