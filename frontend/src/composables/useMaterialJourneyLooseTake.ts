import {
  deleteActivityPackContainerItem,
  updateActivityPackContainerItem,
  type ActivityPackContainerItem,
} from '@/api/activityContainers'
import { postMovePackItem, type ActivityPackItem } from '@/api/activityPackItems'
import { isNonActionableContainerLine } from '@/components/activities/packShellCrateHelpers'
import { computeContainerLineRemainingAtForwardStage } from '@/components/activities/packStageQuantityLayer'
import { getBackendStage, type PackStage } from '@/components/activities/packStageQuantities'

export type LooseTakeConfirmPayload = {
  quantity: number
}

export function containerLineLooseTakeMax(
  ci: ActivityPackContainerItem,
  packStage: PackStage,
): number {
  return computeContainerLineRemainingAtForwardStage(ci, packStage, isNonActionableContainerLine)
}

export async function issueContainerLineLoose(
  activityId: string,
  containerId: string,
  ci: ActivityPackContainerItem,
  quantity: number,
  packItems: ActivityPackItem[],
  packStage: PackStage,
): Promise<void> {
  const pi = packItems.find((p) => p.materialItemId === ci.material_item_id)
  if (!pi) {
    throw new Error('Pack line not found')
  }

  const max = containerLineLooseTakeMax(ci, packStage)
  const qty = Math.min(Math.max(1, Math.floor(quantity)), max)
  if (qty < 1) return

  await postMovePackItem(activityId, pi.id, {
    stage: getBackendStage(packStage),
    quantity: qty,
  })

  if (packStage === 'at_event_transport_back') {
    await updateActivityPackContainerItem(activityId, containerId, ci.id, {
      quantity_transport_back: (ci.quantity_transport_back ?? 0) + qty,
    })
    return
  }

  const packedBefore = ci.quantity_packed ?? 0
  const newPacked = Math.max(0, packedBefore - qty)
  if (newPacked <= 0) {
    await deleteActivityPackContainerItem(activityId, containerId, ci.id)
  } else {
    await updateActivityPackContainerItem(activityId, containerId, ci.id, {
      quantity_packed: newPacked,
    })
  }
}
