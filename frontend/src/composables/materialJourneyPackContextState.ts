import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  isPhysicalComboPackItem,
} from '@/components/activities/packMaterialDisplay'
import {
  isNonActionableContainerLine,
  isCrateShellPackItem,
  packShellContainerForPackItem,
  shellPackItemForContainerId,
} from '@/components/activities/packShellCrateHelpers'
import {
  computeContainerStillAtEventQtyForMaterial,
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
  isPackForwardToEventStage,
  isPackReturnOrUnpackWarehouseStage,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { showPackContainersForProfile } from '@/components/activities/packWorkflowProfile'
import type { PackWorkflowContainerContext, PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import { shouldIncludePackItemOnStageLeft } from '@/components/activities/packWorkflowRules'
import {
  consumableBookedConsumptionQty as consumableBookedConsumptionQtyFor,
  consumableConsumptionRemaining as consumableConsumptionRemainingFor,
  consumablePhysicalReturnMax as consumablePhysicalReturnMaxFor,
} from '@/utils/materialJourneyConsumable'
import { consumableShowsZeroOnStageLeft } from '@/utils/packConsumablePipeline'
import {
  notTakenQtyForReturnPipeline,
  notTakenToEventQtyForMaterial as notTakenToEventQtyFromIssues,
} from '@/components/activities/packNotTakenHelpers'
import {
  containerInnerPendingStoreUnits as unpackContainerInnerPendingStoreUnits,
  containerLinePhysicalReturnRemaining as unpackContainerLinePhysicalReturnRemaining,
  containerLineRemainingStore as unpackContainerLineRemainingStore,
  containerShellOnlyPendingUnpack as unpackContainerShellOnlyPendingUnpack,
  containerShellPendingStoreQty as unpackContainerShellPendingStoreQty,
  pendingStoreLooseQtyForPackItem as unpackPendingStoreLooseQtyForPackItem,
  retourAccountingForContainerLine as unpackRetourAccountingForContainerLine,
  retourAccountingForUnpackLoose as unpackRetourAccountingForUnpackLoose,
  type MaterialJourneyUnpackAccountingInput,
} from '@/utils/materialJourneyUnpackAccounting'

export type MaterialJourneyPackContextStateInput = {
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  packStage: PackStage
  profile: PackWorkflowProfile
  issues?: ActivityIssueReportRow[]
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
  containerContentActionableUnits: (containerId: string) => number
  containerLineRemainingStore: (ci: ActivityPackContainerItem) => number
  containerInnerPendingStoreUnits: (containerId: string) => number
  containerShellPendingStoreQty: (containerId: string) => number
  containerShellOnlyPendingUnpack: (containerId: string) => boolean
  unpackAccountingInput: MaterialJourneyUnpackAccountingInput
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
    return shellPackItemForContainerId(containerId, input.packContainers, input.packItems)
  }

  function crateCheckGapForMaterial(_materialItemId: string): number {
    return 0
  }

  const issues = input.issues ?? []

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

  const unpackAccountingInput: MaterialJourneyUnpackAccountingInput = {
    packItems: input.packItems,
    containerItemsByContainerId: input.containerItemsByContainerId,
    issues,
    packQuantityCtx,
  }

  packQuantityCtx.containerLinePhysicalReturnRemaining = (ci) => {
    if (!isPackReturnStage(input.packStage)) return null
    if (input.packStage === 'transport_back_returned' && input.profile === 'logistics') return null
    return unpackContainerLinePhysicalReturnRemaining(ci, unpackAccountingInput)
  }

  function retourAccountingForUnpackLoose(pi: ActivityPackItem) {
    return unpackRetourAccountingForUnpackLoose(pi, unpackAccountingInput)
  }

  function journeyConsumablePhysicalReturnMax(pi: ActivityPackItem): number {
    const atEvent = computeLooseQtyStillAtEventForReturn(pi, packQuantityCtx)
    if (!pi.isConsumable) return atEvent
    return consumablePhysicalReturnMaxFor(pi, packQuantityCtx, issues)
  }

  const containerIds = input.packContainers.map((c) => c.id)

  const packQuantityForwardMaxCtx: PackQuantityForwardMaxContext = {
    ...packQuantityCtx,
    retourAccountingForUnpackLoose,
    isCrateShellPackItem: (pi) => isCrateShellPackItem(pi, input.packContainers),
    consumablePhysicalReturnMax: journeyConsumablePhysicalReturnMax,
    pendingStoreLooseQtyForPackItem: (pi) =>
      isPackUnpackStage(input.packStage)
        ? unpackPendingStoreLooseQtyForPackItem(pi, containerIds, unpackAccountingInput)
        : Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0) - (pi.quantityWet ?? 0)),
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

  function containerHasPackedInnerLines(containerId: string): boolean {
    return (input.containerItemsByContainerId[containerId] ?? []).some(
      (ci) =>
        !isNonActionableContainerLine(ci) &&
        Math.max(ci.quantity_packed ?? 0, ci.quantity_issued ?? 0) > 0,
    )
  }

  function containerStillAtEventQtyForMaterial(materialItemId: string): number {
    return computeContainerStillAtEventQtyForMaterial(packQuantityCtx, materialItemId)
  }

  function consumableStillOnlyInCrateAtReturn(pi: ActivityPackItem): boolean {
    if (!isPackReturnStage(input.packStage)) return false
    if (containerStillAtEventQtyForMaterial(pi.materialItemId) <= 0) return false
    return computeLooseQtyStillAtEventForReturn(pi, packQuantityCtx) <= 0
  }

  function containerReturnedContentUnits(containerId: string): number {
    let sum = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      sum += ci.quantity_returned ?? 0
    }
    const sh = shellPackItemForContainer(containerId)
    if (sh) sum += sh.quantityReturned ?? 0
    return sum
  }

  function containerReturnedAsWhole(containerId: string): boolean {
    if (containerReturnableUnits(containerId) > 0) return false
    if (containerReturnedContentUnits(containerId) <= 0) return false

    const sh = shellPackItemForContainer(containerId)
    const shellReturned = (sh?.quantityReturned ?? 0) > 0
    const innerReturned = (input.containerItemsByContainerId[containerId] ?? []).some(
      (ci) => !isNonActionableContainerLine(ci) && (ci.quantity_returned ?? 0) > 0,
    )

    if (sh && isPhysicalComboPackItem(sh)) {
      return shellReturned
    }
    if (!containerHasPackedInnerLines(containerId)) {
      return false
    }
    return shellReturned && innerReturned
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
    consumableShowsZeroOnStageLeft: (pi) =>
      consumableShowsZeroOnStageLeft(
        pi,
        input.packStage,
        issues,
        effectiveStageLeftQty(pi),
        stageRightQty(pi),
      ),
    consumableConsumptionRemaining: (pi) => consumableConsumptionRemainingFor(pi, issues),
    consumablePhysicalReturnMax: journeyConsumablePhysicalReturnMax,
    looseQtyStillAtEventForReturn: (pi) => computeLooseQtyStillAtEventForReturn(pi, packQuantityCtx),
    pendingStoreLooseQtyForPackItem: (pi) =>
      isPackUnpackStage(input.packStage)
        ? unpackPendingStoreLooseQtyForPackItem(pi, containerIds, unpackAccountingInput)
        : Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0) - (pi.quantityWet ?? 0)),
    returnedLooseQtyForPackItem: (pi) => pi.quantityReturned ?? 0,
    storedLooseQtyForPackItem: (pi) => pi.quantityStored ?? 0,
    storedShellLooseQtyForPackItem: () => 0,
    looseQtyOnRightMirror: (pi) => computeLooseQtyOnRightMirror(pi, packQuantityCtx),
    looseTransportBackOnRight: () => 0,
    notTakenQtyForReturn: (pi) => {
      if (!isPackReturnOrUnpackWarehouseStage(input.packStage)) return 0
      const packedInCrates = computeQtyInContainersForItem(pi, packQuantityCtx)
      return notTakenQtyForReturnPipeline(pi, {
        packedInCrates,
        consumedQty: pi.isConsumable ? consumableBookedConsumptionQtyFor(pi, issues) : 0,
      })
    },
    notTakenToEventQtyForMaterial: (materialItemId) =>
      notTakenToEventQtyFromIssues(materialItemId, issues),
    consumableStillOnlyInCrateAtReturn,
    consumableBookedConsumptionQty: (pi) => consumableBookedConsumptionQtyFor(pi, issues),
    isIndividuallyStorableCrateShell: () => false,
    containerReturnedAsWhole,
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

  function containerInnerTransportBackReturnableUnits(containerId: string): number {
    let sum = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if (isNonActionableContainerLine(ci)) continue
      sum += Math.max(0, (ci.quantity_transport_back ?? 0) - (ci.quantity_returned ?? 0))
    }
    return sum
  }

  function containerShellTransportBackReturnableUnits(containerId: string): number {
    const sh = shellPackItemForContainer(containerId)
    if (!sh) return 0
    return Math.max(0, (sh.quantityTransportBack ?? 0) - (sh.quantityReturned ?? 0))
  }

  function containerTransportBackReturnableUnits(containerId: string): number {
    const inner = containerInnerTransportBackReturnableUnits(containerId)
    if (inner > 0) return inner
    return containerShellTransportBackReturnableUnits(containerId)
  }

  function containerReturnableUnits(containerId: string): number {
    if (input.packStage === 'transport_back_returned' && input.profile === 'logistics') {
      return containerTransportBackReturnableUnits(containerId)
    }
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
    if (isPackUnpackStage(input.packStage)) {
      return (
        unpackContainerInnerPendingStoreUnits(containerId, unpackAccountingInput) +
        unpackContainerShellPendingStoreQty(
          containerId,
          shellPackItemForContainer(containerId),
          unpackAccountingInput,
          input.packContainers,
        )
      )
    }
    let sum = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if (isNonActionableContainerLine(ci)) continue
      sum += Math.max(
        0,
        (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0) - (ci.quantity_wet ?? 0),
      )
    }
    const sh = shellPackItemForContainer(containerId)
    if (sh) {
      sum += Math.max(
        0,
        (sh.quantityReturned ?? 0) - (sh.quantityStored ?? 0) - (sh.quantityWet ?? 0),
      )
    }
    return sum
  }

  function containerLineRemainingStore(ci: ActivityPackContainerItem): number {
    if (!isPackUnpackStage(input.packStage)) {
      return Math.max(
        0,
        (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0) - (ci.quantity_wet ?? 0),
      )
    }
    return unpackContainerLineRemainingStore(ci, unpackAccountingInput)
  }

  function containerInnerPendingStoreUnits(containerId: string): number {
    return unpackContainerInnerPendingStoreUnits(containerId, unpackAccountingInput)
  }

  function containerShellPendingStoreQty(containerId: string): number {
    return unpackContainerShellPendingStoreQty(
      containerId,
      shellPackItemForContainer(containerId),
      unpackAccountingInput,
      input.packContainers,
    )
  }

  function containerShellOnlyPendingUnpack(containerId: string): boolean {
    return unpackContainerShellOnlyPendingUnpack(
      containerId,
      shellPackItemForContainer(containerId),
      unpackAccountingInput,
    )
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

  function containerContentActionableUnits(containerId: string): number {
    if (isPackReturnStage(input.packStage)) {
      if (input.packStage === 'transport_back_returned' && input.profile === 'logistics') {
        return containerInnerTransportBackReturnableUnits(containerId)
      }
      let inner = 0
      for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
        if (isNonActionableContainerLine(ci)) continue
        inner += computeContainerLineRemainingReturn(ci, packQuantityCtx, containerId)
      }
      return inner
    }
    if (isPackUnpackStage(input.packStage)) {
      return unpackContainerInnerPendingStoreUnits(containerId, unpackAccountingInput)
    }
    let sum = 0
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      sum += containerLineRemainingAtForwardStage(ci)
    }
    return sum
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
    containerReturnableUnits,
    containerStoreUnits,
    containerTransportBackReturnableUnits,
    containerContentsTravelWithShellAtEvent: (containerId) =>
      isPackForwardToEventStage(input.packStage) && containerHasIssuedAtEvent(containerId),
    containerItemsForContainer: (containerId) => input.containerItemsByContainerId[containerId] ?? [],
    containerReturnedAsWhole,
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
    containerContentActionableUnits,
    containerLineRemainingStore,
    containerInnerPendingStoreUnits,
    containerShellPendingStoreQty,
    containerShellOnlyPendingUnpack,
    unpackAccountingInput,
    packCrateLabelsForPackItem,
    qtyInPackCrateForPackItem,
    packCrateAssignQtyForItem,
    stageLeftQty,
    stageRightQty,
    effectiveStageLeftQty,
    packQuantityCtx,
  }
}
