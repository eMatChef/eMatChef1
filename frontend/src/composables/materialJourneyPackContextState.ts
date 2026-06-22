import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  isNonActionableContainerLine,
  isCrateShellPackItem,
  packShellContainerForPackItem,
} from '@/components/activities/packShellCrateHelpers'
import {
  computeContainerLineRemainingReturn,
  computeContainerLineRemainingAtForwardStage,
  computeContainerShellIssueableUnits,
  computeEffectiveStageLeftQty,
  computeIssuedQtyInContainersForMaterial,
  computeLooseQtyForPackItem,
  computeLooseQtyOnRightMirror,
  computeLooseQtyStillAtEventForReturn,
  computePackIssueForwardMax,
  computeQtyInContainersForItem,
  computeTransportBackQtyInContainersForMaterial,
  computeTransportToQtyInContainersForMaterial,
  type PackQuantityContext,
  type PackQuantityForwardMaxContext,
} from '@/components/activities/packStageQuantityLayer'
import {
  getStageLeftQty,
  getStageRightQty,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { showPackContainersForProfile } from '@/components/activities/packWorkflowProfile'
import type { PackWorkflowContainerContext, PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import { shouldIncludePackItemOnStageLeft } from '@/components/activities/packWorkflowRules'

export type MaterialJourneyPackContextStateInput = {
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  packStage: PackStage
  profile: PackWorkflowProfile
}

export type MaterialJourneyPackContextState = {
  packListCtx: PackWorkflowListContext
  packContainerCtx: PackWorkflowContainerContext
  packIssueForwardMax: (pi: ActivityPackItem) => number
  stageLeftItems: ActivityPackItem[]
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  containerIssueableUnits: (containerId: string) => number
  containerReturnableUnits: (containerId: string) => number
  containerStoreUnits: (containerId: string) => number
  containerActionableUnits: (containerId: string) => number
  packCrateLabelsForPackItem: (pi: ActivityPackItem) => string[]
  qtyInPackCrateForPackItem: (pi: ActivityPackItem) => number
  packCrateAssignQtyForItem: (pi: ActivityPackItem) => number
  stageLeftQty: (pi: ActivityPackItem) => number
  stageRightQty: (pi: ActivityPackItem) => number
  effectiveStageLeftQty: (pi: ActivityPackItem) => number
  packQuantityCtx: PackQuantityContext
}

export function createMaterialJourneyPackContextState(
  input: MaterialJourneyPackContextStateInput,
): MaterialJourneyPackContextState {
  const virtualContainerIdByPackItemId: Record<string, string> = {}

  const packContainerBatchCountByMaterialItemId: Record<string, number> = {}
  for (const c of input.packContainers) {
    if (!(c.container_batch_id ?? '').trim()) continue
    const directMid = (c.container_material_item_id ?? '').trim()
    if (directMid) {
      packContainerBatchCountByMaterialItemId[directMid] =
        (packContainerBatchCountByMaterialItemId[directMid] ?? 0) + 1
      continue
    }
    for (const pi of input.packItems) {
      if (!isCrateShellPackItem(pi, input.packContainers)) continue
      if (packShellContainerForPackItem(pi, input.packContainers)?.id === c.id) {
        packContainerBatchCountByMaterialItemId[pi.materialItemId] =
          (packContainerBatchCountByMaterialItemId[pi.materialItemId] ?? 0) + 1
        break
      }
    }
  }

  const assignedQtyByMaterialId: Record<string, number> = {}
  for (const c of input.packContainers) {
    for (const it of input.containerItemsByContainerId[c.id] ?? []) {
      const mid = it.material_item_id
      assignedQtyByMaterialId[mid] = (assignedQtyByMaterialId[mid] ?? 0) + (it.quantity_packed ?? 0)
    }
  }

  function shellPackItemForContainer(containerId: string): ActivityPackItem | undefined {
    const c = input.packContainers.find((x) => x.id === containerId)
    if (!c) return undefined
    const mid = (c.container_material_item_id ?? '').trim()
    if (mid) {
      const byMid = input.packItems.find((p) => p.materialItemId === mid)
      if (byMid) return byMid
    }
    const bid = (c.container_batch_id ?? '').trim()
    if (bid) {
      const byBatch = input.packItems.find((p) => (p.linkedContainerBatchId ?? '').trim() === bid)
      if (byBatch) return byBatch
    }
    for (const pi of input.packItems) {
      if (packShellContainerForPackItem(pi, input.packContainers)?.id === containerId) {
        return pi
      }
    }
    return undefined
  }

  function crateCheckGapForMaterial(_materialItemId: string): number {
    return 0
  }

  function retourAccountingForUnpackLoose(pi: ActivityPackItem) {
    return { retourTotal: pi.quantityReturned ?? 0 }
  }

  const packQuantityCtx: PackQuantityContext = {
    stage: input.packStage,
    profile: input.profile,
    packContainers: input.packContainers,
    containerItemsByContainerId: input.containerItemsByContainerId,
    assignedQtyByMaterialId,
    packContainerBatchCountByMaterialItemId,
    virtualContainerIdByPackItemId,
    shellPackItemForContainer,
    isNonActionableContainerLine,
    crateCheckGapForMaterial,
  }

  const packQuantityForwardMaxCtx: PackQuantityForwardMaxContext = {
    ...packQuantityCtx,
    retourAccountingForUnpackLoose,
    isCrateShellPackItem: (pi) => isCrateShellPackItem(pi, input.packContainers),
    consumablePhysicalReturnMax: (pi) => getStageLeftQty(pi, input.packStage, input.profile),
    pendingStoreLooseQtyForPackItem: (pi) =>
      Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0)),
  }

  function effectiveStageLeftQty(p: ActivityPackItem): number {
    return computeEffectiveStageLeftQty(p, {
      ...packQuantityCtx,
      retourAccountingForUnpackLoose,
    })
  }

  function stageLeftQty(p: ActivityPackItem): number {
    return getStageLeftQty(p, input.packStage, input.profile)
  }

  function stageRightQty(p: ActivityPackItem): number {
    return getStageRightQty(p, input.packStage, input.profile)
  }

  function looseQtyForPackItem(p: ActivityPackItem): number {
    return computeLooseQtyForPackItem(p, packQuantityCtx)
  }

  function packIssueForwardMax(pi: ActivityPackItem): number {
    return computePackIssueForwardMax(pi, packQuantityForwardMaxCtx)
  }

  const packListCtx: PackWorkflowListContext = {
    stage: input.packStage,
    profile: input.profile,
    showPackContainersUi: showPackContainersForProfile(input.profile, input.packStage),
    packContainers: input.packContainers,
    virtualContainerIdByPackItemId,
    hasPackContainers: input.packContainers.length > 0,
    effectiveStageLeftQty,
    getStageLeftQty: stageLeftQty,
    getStageRightQty: stageRightQty,
    looseQtyForPackItem,
    consumableShowsZeroOnStageLeft: () => false,
    consumableConsumptionRemaining: () => 0,
    consumablePhysicalReturnMax: (pi) => stageLeftQty(pi),
    looseQtyStillAtEventForReturn: (pi) => computeLooseQtyStillAtEventForReturn(pi, packQuantityCtx),
    pendingStoreLooseQtyForPackItem: (pi) =>
      Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0)),
    returnedLooseQtyForPackItem: (pi) => pi.quantityReturned ?? 0,
    storedLooseQtyForPackItem: (pi) => pi.quantityStored ?? 0,
    storedShellLooseQtyForPackItem: () => 0,
    looseQtyOnRightMirror: (pi) => computeLooseQtyOnRightMirror(pi, packQuantityCtx),
    looseTransportBackOnRight: () => 0,
    notTakenQtyForReturn: () => 0,
    notTakenToEventQtyForMaterial: () => 0,
    consumableStillOnlyInCrateAtReturn: () => false,
    consumableBookedConsumptionQty: () => 0,
    isIndividuallyStorableCrateShell: () => false,
    containerReturnedAsWhole: () => false,
    qtyInContainersForItem: (pi) => computeQtyInContainersForItem(pi, packQuantityCtx),
    issuedQtyInContainersForMaterial: (materialItemId) =>
      computeIssuedQtyInContainersForMaterial(packQuantityCtx, materialItemId),
    transportToQtyInContainersForMaterial: (materialItemId) =>
      computeTransportToQtyInContainersForMaterial(packQuantityCtx, materialItemId),
    transportBackQtyInContainersForMaterial: (materialItemId) =>
      computeTransportBackQtyInContainersForMaterial(packQuantityCtx, materialItemId),
    isConsumablePackLine: (pi) => pi.isConsumable,
  }

  function containerHasPackedContent(containerId: string): boolean {
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if ((ci.quantity_packed ?? 0) > 0) return true
    }
    const sh = shellPackItemForContainer(containerId)
    return (sh?.quantityPacked ?? 0) > 0
  }

  function containerHasIssuedAtEvent(containerId: string): boolean {
    const sh = shellPackItemForContainer(containerId)
    if (sh != null) return (sh.quantityIssued ?? 0) > 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if ((ci.quantity_issued ?? 0) > 0) return true
    }
    return false
  }

  function containerLineRemainingAtForwardStage(ci: ActivityPackContainerItem): number {
    return computeContainerLineRemainingAtForwardStage(
      ci,
      input.packStage,
      isNonActionableContainerLine,
    )
  }

  function containerIssueableUnits(containerId: string): number {
    let sum = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      sum += containerLineRemainingAtForwardStage(ci)
    }
    return sum + computeContainerShellIssueableUnits(containerId, packQuantityCtx)
  }

  function containerReturnableUnits(containerId: string): number {
    let inner = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if (isNonActionableContainerLine(ci)) continue
      inner += computeContainerLineRemainingReturn(ci, packQuantityCtx, containerId)
    }
    if (inner > 0) return inner
    const sh = shellPackItemForContainer(containerId)
    if (!sh) return 0
    return Math.max(0, (sh.quantityIssued ?? 0) - (sh.quantityReturned ?? 0))
  }

  function containerStoreUnits(containerId: string): number {
    let sum = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if (isNonActionableContainerLine(ci)) continue
      sum += Math.max(0, (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0))
    }
    const sh = shellPackItemForContainer(containerId)
    if (sh) {
      sum += Math.max(0, (sh.quantityReturned ?? 0) - (sh.quantityStored ?? 0))
    }
    return sum
  }

  function containerActionableUnits(containerId: string): number {
    if (isPackReturnStage(input.packStage)) {
      return containerReturnableUnits(containerId)
    }
    if (isPackUnpackStage(input.packStage)) {
      return containerStoreUnits(containerId)
    }
    return containerIssueableUnits(containerId)
  }

  const stageLeftItems = input.packItems.filter((p) =>
    shouldIncludePackItemOnStageLeft(p, packListCtx),
  )

  const packContainerCtx: PackWorkflowContainerContext = {
    ...packListCtx,
    stageLeftItemIds: new Set(stageLeftItems.map((p) => p.id)),
    getLeftQtyForMerge: stageLeftQty,
    shellPackItemForContainer,
    containerHasPackedContent,
    containerHasIssuedAtEvent,
    containerLineRemainingAtForwardStage,
    containerItemsForContainer: (containerId) => input.containerItemsByContainerId[containerId] ?? [],
  }

  function packCrateLabelsForPackItem(pi: ActivityPackItem): string[] {
    const labels: string[] = []
    const seen = new Set<string>()
    for (const c of input.packContainers) {
      const items = input.containerItemsByContainerId[c.id] ?? []
      const hasMaterial = items.some(
        (row) => row.material_item_id === pi.materialItemId && (row.quantity_packed ?? 0) > 0,
      )
      if (hasMaterial && !seen.has(c.id)) {
        seen.add(c.id)
        labels.push(c.label)
      }
    }
    return labels
  }

  function qtyInPackCrateForPackItem(pi: ActivityPackItem): number {
    return packListCtx.qtyInContainersForItem(pi)
  }

  function packCrateAssignQtyForItem(pi: ActivityPackItem): number {
    const forward = packIssueForwardMax(pi)
    if (forward > 0) return forward
    const packed = stageRightQty(pi)
    const inCrate = qtyInPackCrateForPackItem(pi)
    return Math.max(0, packed - inCrate)
  }

  return {
    packListCtx,
    packContainerCtx,
    packIssueForwardMax,
    stageLeftItems,
    shellPackItemForContainer,
    containerIssueableUnits,
    containerReturnableUnits,
    containerStoreUnits,
    containerActionableUnits,
    packCrateLabelsForPackItem,
    qtyInPackCrateForPackItem,
    packCrateAssignQtyForItem,
    stageLeftQty,
    stageRightQty,
    effectiveStageLeftQty,
    packQuantityCtx,
  }
}
