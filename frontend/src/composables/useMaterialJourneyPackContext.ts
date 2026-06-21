import { computed, type Ref } from 'vue'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isNonActionableContainerLine, isCrateShellPackItem, packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
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
  computeRightQtyForMoveBack,
  computeTransportBackQtyInContainersForMaterial,
  computeTransportToQtyInContainersForMaterial,
  type PackQuantityContext,
  type PackQuantityForwardMaxContext,
  type PackQuantityMoveBackContext,
} from '@/components/activities/packStageQuantityLayer'
import { getStageLeftQty,
  getStageRightQty,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { showPackContainersForProfile } from '@/components/activities/packWorkflowProfile'
import type { PackWorkflowListContext, PackWorkflowContainerContext } from '@/components/activities/packWorkflowRules'
import { shouldIncludePackItemOnStageLeft } from '@/components/activities/packWorkflowRules'

export function useMaterialJourneyPackContext(options: {
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  packStage: Ref<PackStage>
  profile: Ref<PackWorkflowProfile>
}) {
  const virtualContainerIdByPackItemId = computed(() => ({} as Record<string, string>))

  const packContainerBatchCountByMaterialItemId = computed(() => {
    const m: Record<string, number> = {}
    for (const c of options.packContainers.value) {
      if (!(c.container_batch_id ?? '').trim()) continue
      const directMid = (c.container_material_item_id ?? '').trim()
      if (directMid) {
        m[directMid] = (m[directMid] ?? 0) + 1
        continue
      }
      for (const pi of options.packItems.value) {
        if (!isCrateShellPackItem(pi, options.packContainers.value)) continue
        if (packShellContainerForPackItem(pi, options.packContainers.value)?.id === c.id) {
          m[pi.materialItemId] = (m[pi.materialItemId] ?? 0) + 1
          break
        }
      }
    }
    return m
  })

  const assignedQtyByMaterialId = computed(() => {
    const m: Record<string, number> = {}
    for (const c of options.packContainers.value) {
      for (const it of options.containerItemsByContainerId.value[c.id] ?? []) {
        const mid = it.material_item_id
        m[mid] = (m[mid] ?? 0) + (it.quantity_packed ?? 0)
      }
    }
    return m
  })

  function shellPackItemForContainer(containerId: string): ActivityPackItem | undefined {
    const c = options.packContainers.value.find((x) => x.id === containerId)
    if (!c) return undefined
    const mid = (c.container_material_item_id ?? '').trim()
    if (mid) {
      const byMid = options.packItems.value.find((p) => p.materialItemId === mid)
      if (byMid) return byMid
    }
    const bid = (c.container_batch_id ?? '').trim()
    if (bid) {
      const byBatch = options.packItems.value.find(
        (p) => (p.linkedContainerBatchId ?? '').trim() === bid,
      )
      if (byBatch) return byBatch
    }
    for (const pi of options.packItems.value) {
      if (packShellContainerForPackItem(pi, options.packContainers.value)?.id === containerId) {
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

  const packQuantityCtx = computed(
    (): PackQuantityContext => ({
      stage: options.packStage.value,
      profile: options.profile.value,
      packContainers: options.packContainers.value,
      containerItemsByContainerId: options.containerItemsByContainerId.value,
      assignedQtyByMaterialId: assignedQtyByMaterialId.value,
      packContainerBatchCountByMaterialItemId: packContainerBatchCountByMaterialItemId.value,
      virtualContainerIdByPackItemId: virtualContainerIdByPackItemId.value,
      shellPackItemForContainer,
      isNonActionableContainerLine,
      crateCheckGapForMaterial,
    }),
  )

  const packQuantityForwardMaxCtx = computed(
    (): PackQuantityForwardMaxContext => ({
      ...packQuantityCtx.value,
      retourAccountingForUnpackLoose,
      isCrateShellPackItem: (pi) => isCrateShellPackItem(pi, options.packContainers.value),
      consumablePhysicalReturnMax: (pi) =>
        getStageLeftQty(pi, options.packStage.value, options.profile.value),
      pendingStoreLooseQtyForPackItem: (pi) =>
        Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0)),
    }),
  )

  const packQuantityMoveBackCtx = computed(
    (): PackQuantityMoveBackContext => ({
      ...packQuantityCtx.value,
      isCrateShellPackItem: (pi) => isCrateShellPackItem(pi, options.packContainers.value),
      storedLooseQtyForPackItem: (pi) => pi.quantityStored ?? 0,
      returnedLooseQtyForPackItem: (pi) => pi.quantityReturned ?? 0,
    }),
  )

  function rightQtyForMoveBack(pi: ActivityPackItem): number {
    return computeRightQtyForMoveBack(pi, packQuantityMoveBackCtx.value)
  }

  function effectiveStageLeftQty(p: ActivityPackItem): number {
    return computeEffectiveStageLeftQty(p, {
      ...packQuantityCtx.value,
      retourAccountingForUnpackLoose,
    })
  }

  function stageLeftQty(p: ActivityPackItem): number {
    return getStageLeftQty(p, options.packStage.value, options.profile.value)
  }

  function stageRightQty(p: ActivityPackItem): number {
    return getStageRightQty(p, options.packStage.value, options.profile.value)
  }

  function looseQtyForPackItem(p: ActivityPackItem): number {
    return computeLooseQtyForPackItem(p, packQuantityCtx.value)
  }

  function packIssueForwardMax(pi: ActivityPackItem): number {
    return computePackIssueForwardMax(pi, packQuantityForwardMaxCtx.value)
  }

  const packListCtx = computed(
    (): PackWorkflowListContext => ({
      stage: options.packStage.value,
      profile: options.profile.value,
      showPackContainersUi: showPackContainersForProfile(options.profile.value, options.packStage.value),
      packContainers: options.packContainers.value,
      virtualContainerIdByPackItemId: virtualContainerIdByPackItemId.value,
      hasPackContainers: options.packContainers.value.length > 0,
      effectiveStageLeftQty,
      getStageLeftQty: stageLeftQty,
      getStageRightQty: stageRightQty,
      looseQtyForPackItem,
      consumableShowsZeroOnStageLeft: () => false,
      consumableConsumptionRemaining: () => 0,
      consumablePhysicalReturnMax: (pi) => stageLeftQty(pi),
      looseQtyStillAtEventForReturn: (pi) =>
        computeLooseQtyStillAtEventForReturn(pi, packQuantityCtx.value),
      pendingStoreLooseQtyForPackItem: (pi) =>
        Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0)),
      returnedLooseQtyForPackItem: (pi) => pi.quantityReturned ?? 0,
      storedLooseQtyForPackItem: (pi) => pi.quantityStored ?? 0,
      storedShellLooseQtyForPackItem: () => 0,
      looseQtyOnRightMirror: (pi) => computeLooseQtyOnRightMirror(pi, packQuantityCtx.value),
      looseTransportBackOnRight: () => 0,
      notTakenQtyForReturn: () => 0,
      notTakenToEventQtyForMaterial: () => 0,
      consumableStillOnlyInCrateAtReturn: () => false,
      consumableBookedConsumptionQty: () => 0,
      isIndividuallyStorableCrateShell: () => false,
      containerReturnedAsWhole: () => false,
      qtyInContainersForItem: (pi) => computeQtyInContainersForItem(pi, packQuantityCtx.value),
      issuedQtyInContainersForMaterial: (materialItemId) =>
        computeIssuedQtyInContainersForMaterial(packQuantityCtx.value, materialItemId),
      transportToQtyInContainersForMaterial: (materialItemId) =>
        computeTransportToQtyInContainersForMaterial(packQuantityCtx.value, materialItemId),
      transportBackQtyInContainersForMaterial: (materialItemId) =>
        computeTransportBackQtyInContainersForMaterial(packQuantityCtx.value, materialItemId),
      isConsumablePackLine: (pi) => pi.isConsumable,
    }),
  )

  function containerHasPackedContent(containerId: string): boolean {
    for (const ci of options.containerItemsByContainerId.value[containerId] ?? []) {
      if ((ci.quantity_packed ?? 0) > 0) return true
    }
    const sh = shellPackItemForContainer(containerId)
    return (sh?.quantityPacked ?? 0) > 0
  }

  function containerHasIssuedAtEvent(containerId: string): boolean {
    const sh = shellPackItemForContainer(containerId)
    if (sh != null) return (sh.quantityIssued ?? 0) > 0
    for (const ci of options.containerItemsByContainerId.value[containerId] ?? []) {
      if ((ci.quantity_issued ?? 0) > 0) return true
    }
    return false
  }

  function containerLineRemainingAtForwardStage(ci: ActivityPackContainerItem): number {
    return computeContainerLineRemainingAtForwardStage(
      ci,
      options.packStage.value,
      isNonActionableContainerLine,
    )
  }

  function containerIssueableUnits(containerId: string): number {
    let sum = 0
    for (const ci of options.containerItemsByContainerId.value[containerId] ?? []) {
      sum += containerLineRemainingAtForwardStage(ci)
    }
    return sum + computeContainerShellIssueableUnits(containerId, packQuantityCtx.value)
  }

  function containerReturnableUnits(containerId: string): number {
    let inner = 0
    for (const ci of options.containerItemsByContainerId.value[containerId] ?? []) {
      if (isNonActionableContainerLine(ci)) continue
      inner += computeContainerLineRemainingReturn(ci, packQuantityCtx.value, containerId)
    }
    if (inner > 0) return inner
    const sh = shellPackItemForContainer(containerId)
    if (!sh) return 0
    return Math.max(0, (sh.quantityIssued ?? 0) - (sh.quantityReturned ?? 0))
  }

  function containerStoreUnits(containerId: string): number {
    let sum = 0
    for (const ci of options.containerItemsByContainerId.value[containerId] ?? []) {
      if (isNonActionableContainerLine(ci)) continue
      if (isPackUnpackStage(options.packStage.value)) {
        sum += Math.max(0, (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0))
      } else {
        sum += Math.max(0, (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0))
      }
    }
    const sh = shellPackItemForContainer(containerId)
    if (sh) {
      sum += Math.max(0, (sh.quantityReturned ?? 0) - (sh.quantityStored ?? 0))
    }
    return sum
  }

  function containerActionableUnits(containerId: string): number {
    if (isPackReturnStage(options.packStage.value)) {
      return containerReturnableUnits(containerId)
    }
    if (isPackUnpackStage(options.packStage.value)) {
      return containerStoreUnits(containerId)
    }
    return containerIssueableUnits(containerId)
  }

  const stageLeftItems = computed(() =>
    options.packItems.value.filter((p) => shouldIncludePackItemOnStageLeft(p, packListCtx.value)),
  )

  const packContainerCtx = computed(
    (): PackWorkflowContainerContext => ({
      ...packListCtx.value,
      stageLeftItemIds: new Set(stageLeftItems.value.map((p) => p.id)),
      getLeftQtyForMerge: stageLeftQty,
      shellPackItemForContainer,
      containerHasPackedContent,
      containerHasIssuedAtEvent,
      containerLineRemainingAtForwardStage,
      containerItemsForContainer: (containerId) =>
        options.containerItemsByContainerId.value[containerId] ?? [],
    }),
  )

  function packCrateLabelsForPackItem(pi: ActivityPackItem): string[] {
    const labels: string[] = []
    const seen = new Set<string>()
    for (const c of options.packContainers.value) {
      const items = options.containerItemsByContainerId.value[c.id] ?? []
      const hasMaterial = items.some(
        (row) =>
          row.material_item_id === pi.materialItemId &&
          (row.quantity_packed ?? 0) > 0,
      )
      if (hasMaterial && !seen.has(c.id)) {
        seen.add(c.id)
        labels.push(c.label)
      }
    }
    return labels
  }

  function qtyInPackCrateForPackItem(pi: ActivityPackItem): number {
    return packListCtx.value.qtyInContainersForItem(pi)
  }

  /** Menge, die in die gewählte Packkiste gelegt werden kann (offen oder bereits erledigt, noch nicht in Kiste). */
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
    effectiveStageLeftQty,
    stageLeftQty,
    stageRightQty,
    stageLeftItems,
    shellPackItemForContainer,
    containerIssueableUnits,
    containerReturnableUnits,
    containerStoreUnits,
    containerActionableUnits,
    packQuantityCtx,
    packCrateLabelsForPackItem,
    qtyInPackCrateForPackItem,
    packCrateAssignQtyForItem,
    rightQtyForMoveBack,
  }
}
