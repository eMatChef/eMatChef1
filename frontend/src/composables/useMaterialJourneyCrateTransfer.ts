import {
  createActivityPackContainerItem,
  deleteActivityPackContainerItem,
  updateActivityPackContainerItem,
  type ActivityPackContainer,
  type ActivityPackContainerItem,
} from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'

export type ReassignCrateConfirmPayload = {
  targetContainerId: string
  quantity: number
}

/** Ziel-Packkisten für «In andere Packkiste» (ohne Quelle, ohne Phys.-Kombi-Shell). */
export function reassignTargetPackCrates(
  packContainers: ActivityPackContainer[],
  sourceContainerId: string,
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined,
): ActivityPackContainer[] {
  return packContainers.filter((c) => {
    if (c.id === sourceContainerId) return false
    const shell = shellPackItemForContainer(c.id)
    if (shell && isPhysicalComboPackItem(shell)) return false
    return true
  })
}

export async function transferPackedItemBetweenContainers(
  activityId: string,
  sourceContainerId: string,
  targetContainerId: string,
  containerItemId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  quantity?: number,
): Promise<void> {
  if (sourceContainerId === targetContainerId) {
    throw new Error('Source and target container must differ')
  }

  const sourceItems = containerItemsByContainerId[sourceContainerId] ?? []
  const row = sourceItems.find((item) => item.id === containerItemId)
  if (!row) {
    throw new Error('Container item not found')
  }

  const packed = row.quantity_packed ?? 0
  if (packed <= 0) return

  const qty = Math.min(Math.max(1, Math.floor(quantity ?? packed)), packed)
  if (qty <= 0) return

  let targetItems = [...(containerItemsByContainerId[targetContainerId] ?? [])]
  const existing = targetItems.find((t) => t.material_item_id === row.material_item_id)
  if (existing) {
    await updateActivityPackContainerItem(activityId, targetContainerId, existing.id, {
      quantity_packed: (existing.quantity_packed ?? 0) + qty,
    })
  } else {
    await createActivityPackContainerItem(activityId, targetContainerId, {
      material_item_id: row.material_item_id,
      material_batch_id: row.material_batch_id,
      quantity_packed: qty,
    })
  }

  const remaining = packed - qty
  if (remaining <= 0) {
    await deleteActivityPackContainerItem(activityId, sourceContainerId, row.id)
  } else {
    await updateActivityPackContainerItem(activityId, sourceContainerId, row.id, {
      quantity_packed: remaining,
    })
  }
}

export async function transferAllPackedItemsBetweenContainers(
  activityId: string,
  sourceContainerId: string,
  targetContainerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): Promise<void> {
  if (sourceContainerId === targetContainerId) {
    throw new Error('Source and target container must differ')
  }

  const sourceItems = [...(containerItemsByContainerId[sourceContainerId] ?? [])]
  const movable = sourceItems.filter((row) => (row.quantity_packed ?? 0) > 0)
  if (movable.length === 0) return

  let targetItems = [...(containerItemsByContainerId[targetContainerId] ?? [])]

  for (const row of movable) {
    const qty = row.quantity_packed ?? 0
    if (qty <= 0) continue

    const existing = targetItems.find((t) => t.material_item_id === row.material_item_id)
    if (existing) {
      const updated = await updateActivityPackContainerItem(
        activityId,
        targetContainerId,
        existing.id,
        { quantity_packed: (existing.quantity_packed ?? 0) + qty },
      )
      targetItems = targetItems.map((t) => (t.id === updated.id ? updated : t))
    } else {
      const created = await createActivityPackContainerItem(activityId, targetContainerId, {
        material_item_id: row.material_item_id,
        quantity_packed: qty,
      })
      targetItems = [...targetItems, created]
    }

    await deleteActivityPackContainerItem(activityId, sourceContainerId, row.id)
  }
}
