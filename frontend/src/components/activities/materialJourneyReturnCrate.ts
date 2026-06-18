import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import { isNonActionableContainerLine } from '@/components/activities/packShellCrateHelpers'
import type { PackQuantityContext } from '@/components/activities/packStageQuantityLayer'
import { computeContainerLineRemainingReturn } from '@/components/activities/packStageQuantityLayer'
import {
  packReturnCrateMode,
  packWorkflowRole,
} from '@/components/activities/packWorkflowRules'

export function shouldOpenMaterialJourneyReturnCrateModal(
  containerId: string,
  options: {
    canManageMaterials: boolean
    packItems: ActivityPackItem[]
    containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
    packQuantityCtx: PackQuantityContext
    shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  },
): boolean {
  if (packReturnCrateMode(packWorkflowRole(options.canManageMaterials)) === 'full') {
    return true
  }
  for (const ci of options.containerItemsByContainerId[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    const rem = computeContainerLineRemainingReturn(ci, options.packQuantityCtx, containerId)
    if (rem < 1) continue
    const pi = options.packItems.find((p) => p.materialItemId === ci.material_item_id)
    if (pi?.isConsumable) return true
  }
  const shell = options.shellPackItemForContainer(containerId)
  if (shell?.isConsumable) return true
  return false
}

export function buildMaterialJourneyReturnCrateLines(
  container: ActivityPackContainer,
  options: {
    packItems: ActivityPackItem[]
    containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
    packQuantityCtx: PackQuantityContext
    shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
    materialFallbackLabel: string
  },
): ReturnCrateLineEdit[] {
  const containerId = container.id
  const lines: ReturnCrateLineEdit[] = []

  for (const ci of options.containerItemsByContainerId[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    const max = computeContainerLineRemainingReturn(ci, options.packQuantityCtx, containerId)
    const returnedAlready = ci.quantity_returned ?? 0
    if (max < 1 && returnedAlready < 1) continue
    const materialItemId = ci.material_item_id ?? null
    const pi = materialItemId
      ? options.packItems.find((p) => p.materialItemId === materialItemId)
      : undefined
    const isConsumable = Boolean(pi?.isConsumable)
    const isDone = max < 1 && returnedAlready > 0
    lines.push({
      id: ci.id,
      kind: 'line',
      placement: 'in_crate',
      containerItemId: ci.id,
      materialItemId,
      materialName: ci.material_name ?? pi?.materialName ?? options.materialFallbackLabel,
      expectedQty: Math.max(ci.quantity_packed ?? 0, ci.quantity_issued ?? 0),
      max,
      issued: ci.quantity_issued ?? 0,
      returnedAlready,
      included: isDone ? false : !isConsumable,
      qty: isDone || isConsumable ? 0 : max,
      isExtra: false,
      isConsumable,
      consumptionDone: !isConsumable,
      consumptionOpen: 0,
      isDone,
    })
  }

  const shell = options.shellPackItemForContainer(containerId)
  const innerReturnable = lines.reduce((sum, line) => sum + (line.max > 0 ? line.max : 0), 0)
  if (shell && innerReturnable <= 0) {
    const shellMax = Math.max(0, (shell.quantityIssued ?? 0) - (shell.quantityReturned ?? 0))
    if (shellMax > 0) {
      const isConsumable = Boolean(shell.isConsumable)
      lines.push({
        id: 'shell',
        kind: 'shell',
        placement: 'shell',
        materialItemId: shell.materialItemId,
        materialName: shell.materialName,
        expectedQty: 1,
        max: shellMax,
        issued: shellMax,
        returnedAlready: shell.quantityReturned ?? 0,
        included: !isConsumable,
        qty: isConsumable ? 0 : shellMax,
        isExtra: false,
        isConsumable,
        consumptionDone: !isConsumable,
        consumptionOpen: 0,
        isDone: false,
      })
    }
  }

  return lines
}

export function materialJourneyReturnCrateSubmitDisabled(lines: ReturnCrateLineEdit[]): boolean {
  const openConsumables = lines.some(
    (line) => line.isConsumable && !line.consumptionDone && line.consumptionOpen > 0,
  )
  if (openConsumables) return true
  const hasReturnSelection = lines.some((line) => !line.isConsumable && line.included && line.qty > 0)
  if (hasReturnSelection) return false
  return lines.some((line) => !line.isConsumable && line.max > 0)
}

export type MaterialJourneyReturnCrateBatchStep =
  | { kind: 'shell'; qty: number }
  | { kind: 'loose'; materialItemId?: string; qty: number }
  | { kind: 'line'; containerItemId?: string; qty: number }

export function materialJourneyReturnCrateBatchSteps(
  lines: ReturnCrateLineEdit[],
): MaterialJourneyReturnCrateBatchStep[] {
  return lines
    .filter((line) => !line.isConsumable && !line.isDone && line.included && line.qty > 0)
    .map((line): MaterialJourneyReturnCrateBatchStep => {
      if (line.kind === 'shell') {
        return { kind: 'shell', qty: line.qty }
      }
      return { kind: 'line', containerItemId: line.containerItemId, qty: line.qty }
    })
    .sort((a, b) => {
      const rank = (k: MaterialJourneyReturnCrateBatchStep['kind']) => (k === 'shell' ? 2 : k === 'loose' ? 1 : 0)
      return rank(a.kind) - rank(b.kind)
    })
}
