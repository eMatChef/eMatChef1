/**
 * Mengen-Callback-Schicht — lose vs. Kiste, Spiegel, Vorwärts/Rückwärts-Max.
 * Siehe docs/activities/pack-workflow-rules.md · Entscheidungen in packWorkflowRules.ts.
 */
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  crateShellExcludedFromLooseForwardList,
} from '@/components/activities/packWorkflowRules'
import { isPhysicalComboAsSet } from '@/components/activities/packShellCrateHelpers'
import {
  getStageLeftQty,
  getStageRightQty,
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'

export type ContainerQtyField = 'packed' | 'transport_to' | 'issued' | 'transport_back' | 'returned'

/** Live-Daten aus ActivityPackListTab — keine UI, nur Mengen. */
export type PackQuantityContext = {
  stage: PackStage
  profile: PackWorkflowProfile
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  assignedQtyByMaterialId: Record<string, number>
  packContainerBatchCountByMaterialItemId: Record<string, number>
  virtualContainerIdByPackItemId: Record<string, string>
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  isNonActionableContainerLine: (ci: ActivityPackContainerItem) => boolean
  crateCheckGapForMaterial: (materialItemId: string) => number
  /**
   * Physisch noch retournierbar (expectedReturn − returned) inkl. Verbrauch/Verlust/Reparatur.
   * Nur auf Retour-Stufen; `null` = kein Cap (Pipeline issued − returned).
   */
  containerLinePhysicalReturnRemaining?: (ci: ActivityPackContainerItem) => number | null
}

export type PackQuantityMoveBackContext = PackQuantityContext & {
  isCrateShellPackItem: (pi: ActivityPackItem) => boolean
  storedLooseQtyForPackItem: (pi: ActivityPackItem) => number
  returnedLooseQtyForPackItem: (pi: ActivityPackItem) => number
}

export type PackQuantityForwardMaxContext = PackQuantityEffectiveLeftContext & {
  isCrateShellPackItem: (pi: ActivityPackItem) => boolean
  consumablePhysicalReturnMax: (pi: ActivityPackItem) => number
  pendingStoreLooseQtyForPackItem: (pi: ActivityPackItem) => number
}

export type PackQuantityEffectiveLeftContext = PackQuantityContext & {
  retourAccountingForUnpackLoose: (pi: ActivityPackItem) => { retourTotal: number }
}

function stageLeft(pi: ActivityPackItem, ctx: PackQuantityContext, stage?: PackStage): number {
  return getStageLeftQty(pi, stage ?? ctx.stage, ctx.profile)
}

function stageRight(pi: ActivityPackItem, ctx: PackQuantityContext, stage?: PackStage): number {
  return getStageRightQty(pi, stage ?? ctx.stage, ctx.profile)
}

/** Noch in der aktuellen Hinweg-Stufe buchbar (Transport hin vs. Am Event). */
export function computePackItemRemainingAtForwardStage(
  pi: ActivityPackItem,
  stage: PackStage,
): number {
  if (stage === 'packed_transport_to') {
    return Math.max(0, (pi.quantityPacked ?? 0) - (pi.quantityTransportTo ?? 0))
  }
  if (stage === 'transport_to_at_event') {
    return Math.max(0, (pi.quantityTransportTo ?? 0) - (pi.quantityIssued ?? 0))
  }
  if (stage === 'packed_at_event') {
    return Math.max(0, (pi.quantityPacked ?? 0) - (pi.quantityIssued ?? 0))
  }
  if (stage === 'at_event_transport_back') {
    return Math.max(0, (pi.quantityIssued ?? 0) - (pi.quantityTransportBack ?? 0))
  }
  return Math.max(0, (pi.quantityPacked ?? 0) - (pi.quantityIssued ?? 0))
}

export function computeContainerLineRemainingAtForwardStage(
  ci: ActivityPackContainerItem,
  stage: PackStage,
  isNonActionable: (ci: ActivityPackContainerItem) => boolean,
): number {
  if (isNonActionable(ci)) return 0
  const packed = ci.quantity_packed ?? 0
  const transported = ci.quantity_transport_to ?? 0
  const issued = ci.quantity_issued ?? 0
  if (stage === 'packed_transport_to') {
    return Math.max(0, packed - transported)
  }
  if (stage === 'transport_to_at_event') {
    return Math.max(0, transported - issued)
  }
  if (stage === 'packed_at_event') {
    return Math.max(0, packed - issued)
  }
  if (stage === 'at_event_transport_back') {
    const transportBack = ci.quantity_transport_back ?? 0
    return Math.max(0, issued - transportBack)
  }
  return Math.max(0, packed - issued)
}

export function computePackedQtyBaseForContainerSplit(
  pi: ActivityPackItem,
  stage: PackStage,
  profile: PackWorkflowProfile,
): number {
  if (stage === 'confirmed_packed') return getStageRightQty(pi, stage, profile)
  if (stage === 'packed_transport_to') return Math.max(0, pi.quantityPacked ?? 0)
  if (stage === 'transport_to_at_event') return Math.max(0, pi.quantityTransportTo ?? 0)
  if (isPackForwardToEventStage(stage)) return Math.max(0, pi.quantityPacked ?? 0)
  return 0
}

export function computeContainerQtySumForMaterial(
  ctx: PackQuantityContext,
  materialItemId: string,
  field: ContainerQtyField,
): number {
  let sum = 0
  for (const c of ctx.packContainers) {
    for (const ci of ctx.containerItemsByContainerId[c.id] ?? []) {
      if (ci.material_item_id !== materialItemId) continue
      switch (field) {
        case 'packed':
          sum += ci.quantity_packed ?? 0
          break
        case 'transport_to':
          sum += ci.quantity_transport_to ?? 0
          break
        case 'issued':
          sum += ci.quantity_issued ?? 0
          break
        case 'transport_back':
          sum += ci.quantity_transport_back ?? 0
          break
        case 'returned':
          sum += ci.quantity_returned ?? 0
          break
        default:
          break
      }
    }
    const sh = ctx.shellPackItemForContainer(c.id)
    if (sh?.materialItemId === materialItemId) {
      switch (field) {
        case 'packed':
          sum += sh.quantityPacked ?? 0
          break
        case 'transport_to':
          sum += sh.quantityTransportTo ?? 0
          break
        case 'issued':
          sum += sh.quantityIssued ?? 0
          break
        case 'transport_back':
          sum += sh.quantityTransportBack ?? 0
          break
        case 'returned':
          sum += sh.quantityReturned ?? 0
          break
        default:
          break
      }
    }
  }
  return sum
}

export function computeTransportToQtyInContainersForMaterial(
  ctx: PackQuantityContext,
  materialItemId: string,
): number {
  return computeContainerQtySumForMaterial(ctx, materialItemId, 'transport_to')
}

export function computeTransportBackQtyInContainersForMaterial(
  ctx: PackQuantityContext,
  materialItemId: string,
): number {
  return computeContainerQtySumForMaterial(ctx, materialItemId, 'transport_back')
}

export function computeIssuedQtyInContainersForMaterial(
  ctx: PackQuantityContext,
  materialItemId: string,
): number {
  return computeContainerQtySumForMaterial(ctx, materialItemId, 'issued')
}

/** Rest in Kisten/Shell für Hinweg-Stufe (nicht lose). */
export function computeForwardRemainingInContainersForMaterial(
  ctx: PackQuantityContext,
  materialItemId: string,
  stage: PackStage,
): number {
  if (stage !== 'at_event_transport_back' && !isPackForwardToEventStage(stage)) return 0
  let sum = 0
  for (const c of ctx.packContainers) {
    const shell = ctx.shellPackItemForContainer(c.id)
    if (shell?.materialItemId === materialItemId) {
      sum += Math.max(0, getStageLeftQty(shell, stage, ctx.profile))
      continue
    }
    for (const ci of ctx.containerItemsByContainerId[c.id] ?? []) {
      if (ci.material_item_id !== materialItemId) continue
      if (ctx.isNonActionableContainerLine(ci)) continue
      sum += computeContainerLineRemainingAtForwardStage(ci, stage, ctx.isNonActionableContainerLine)
    }
  }
  return sum
}

export function computeLooseQtyForPackItem(
  pi: ActivityPackItem,
  ctx: PackQuantityContext,
  stageOverride?: PackStage,
): number {
  const stage = stageOverride ?? ctx.stage
  if (isPackReturnStage(stage)) return stageRight(pi, ctx, stage)
  if (stage === 'at_event_transport_back') {
    const leftTotal = stageLeft(pi, ctx, stage)
    const inContainers = computeForwardRemainingInContainersForMaterial(
      ctx,
      pi.materialItemId,
      stage,
    )
    return Math.max(0, leftTotal - inContainers)
  }
  if (stage !== 'confirmed_packed' && !isPackForwardToEventStage(stage)) {
    return stageRight(pi, ctx, stage)
  }
  if (
    isPackForwardToEventStage(stage) &&
    crateShellExcludedFromLooseForwardList(
      pi,
      ctx.packContainers,
      true,
      ctx.virtualContainerIdByPackItemId,
      stage,
    )
  ) {
    return 0
  }
  if (
    stage === 'confirmed_packed' &&
    isPhysicalComboAsSet(pi, ctx.packContainers, ctx.virtualContainerIdByPackItemId)
  ) {
    const left = stageLeft(pi, ctx, stage)
    if (left > 0) return left
    const inContainers = ctx.assignedQtyByMaterialId[pi.materialItemId] ?? 0
    return Math.max(0, stageRight(pi, ctx, stage) - inContainers)
  }
  const total =
    stage === 'confirmed_packed'
      ? stageRight(pi, ctx, stage)
      : stage === 'packed_transport_to'
        ? Math.max(0, pi.quantityPacked ?? 0)
        : stage === 'transport_to_at_event'
          ? Math.max(0, pi.quantityTransportTo ?? 0)
          : Math.max(0, pi.quantityPacked ?? 0)
  const assigned = ctx.assignedQtyByMaterialId[pi.materialItemId] ?? 0
  const gap = ctx.crateCheckGapForMaterial(pi.materialItemId)
  const gapAdjust = gap > 0 && assigned > 0 ? gap : 0
  const physicalLoose = Math.max(0, total - assigned - gapAdjust)
  if (stage === 'packed_transport_to') {
    const leftTotal = stageLeft(pi, ctx, stage)
    const inContainers = computeForwardRemainingInContainersForMaterial(
      ctx,
      pi.materialItemId,
      stage,
    )
    return Math.max(0, leftTotal - inContainers)
  }
  if (isPackForwardToEventStage(stage)) {
    if (stage === 'transport_to_at_event') {
      const issuedLoose = Math.max(
        0,
        (pi.quantityIssued ?? 0) -
          computeIssuedQtyInContainersForMaterial(ctx, pi.materialItemId),
      )
      return Math.max(0, physicalLoose - issuedLoose)
    }
    return physicalLoose
  }
  return physicalLoose
}

export function computeQtyInContainersForItem(pi: ActivityPackItem, ctx: PackQuantityContext): number {
  if (!isPackConfirmedStage(ctx.stage) && !isPackForwardToEventStage(ctx.stage)) {
    return 0
  }
  const total = computePackedQtyBaseForContainerSplit(pi, ctx.stage, ctx.profile)
  const assigned = ctx.assignedQtyByMaterialId[pi.materialItemId] ?? 0
  if (isPackConfirmedStage(ctx.stage)) {
    return Math.min(total, assigned)
  }
  return Math.max(0, stageLeft(pi, ctx) - computeLooseQtyForPackItem(pi, ctx))
}

/** Rechte Spalte: lose Menge der aktuellen Hinweg-Stufe. */
export function computeLooseQtyOnRightMirror(pi: ActivityPackItem, ctx: PackQuantityContext): number {
  const stage = ctx.stage
  if (stage === 'packed_transport_to') {
    return Math.max(
      0,
      (pi.quantityTransportTo ?? 0) -
        computeTransportToQtyInContainersForMaterial(ctx, pi.materialItemId),
    )
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    const inContainers = computeIssuedQtyInContainersForMaterial(ctx, pi.materialItemId)
    let loose = Math.max(0, (pi.quantityIssued ?? 0) - inContainers)
    if (loose < 1) return 0
    const gap = ctx.crateCheckGapForMaterial(pi.materialItemId)
    if (gap > 0 && inContainers > 0) {
      loose = Math.max(0, loose - gap)
    }
    return loose
  }
  return stageRight(pi, ctx)
}

export function computeLooseTransportBackOnRight(
  pi: ActivityPackItem,
  ctx: PackQuantityContext,
): number {
  const inContainers = computeTransportBackQtyInContainersForMaterial(ctx, pi.materialItemId)
  return Math.max(0, (pi.quantityTransportBack ?? 0) - inContainers)
}

export function computeLooseIssuedAtEvent(pi: ActivityPackItem, ctx: PackQuantityContext): number {
  if (!isPackForwardToEventStage(ctx.stage)) return stageRight(pi, ctx)
  return computeLooseQtyOnRightMirror(pi, ctx)
}

export function computeContainerLineRemainingReturn(
  ci: ActivityPackContainerItem,
  ctx: PackQuantityContext,
  containerId?: string,
): number {
  if (ctx.isNonActionableContainerLine(ci)) return 0
  if (ctx.stage === 'transport_back_returned' && ctx.profile === 'logistics') {
    return Math.max(0, (ci.quantity_transport_back ?? 0) - (ci.quantity_returned ?? 0))
  }
  const i = ci.quantity_issued ?? 0
  const r = ci.quantity_returned ?? 0
  const issuedRemain = Math.max(0, i - r)
  let base = 0
  if (issuedRemain > 0) {
    base = issuedRemain
  } else if (isPackReturnStage(ctx.stage)) {
    const p = ci.quantity_packed ?? 0
    if (p > r && i <= 0 && containerId) {
      base = p - r
    }
  }
  if (base <= 0) return 0
  if (!isPackReturnStage(ctx.stage)) return base
  const physical = ctx.containerLinePhysicalReturnRemaining?.(ci)
  if (physical == null) return base
  return Math.min(base, Math.max(0, physical))
}

export function computeContainerStillAtEventQtyForMaterial(
  ctx: PackQuantityContext,
  materialItemId: string,
): number {
  let sum = 0
  for (const c of ctx.packContainers) {
    for (const ci of ctx.containerItemsByContainerId[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += computeContainerLineRemainingReturn(ci, ctx, c.id)
      }
    }
  }
  for (const c of ctx.packContainers) {
    if (c.container_material_item_id !== materialItemId) continue
    const sh = ctx.shellPackItemForContainer(c.id)
    if (sh?.materialItemId === materialItemId) {
      sum += Math.max(0, (sh.quantityIssued ?? 0) - (sh.quantityReturned ?? 0))
    }
  }
  return sum
}

export function computeLooseQtyStillOnTransportBackForReturn(
  pi: ActivityPackItem,
  ctx: PackQuantityContext,
): number {
  if (ctx.stage !== 'transport_back_returned' || ctx.profile !== 'logistics') {
    return stageLeft(pi, ctx)
  }
  if (ctx.packContainers.length === 0) return stageLeft(pi, ctx)
  return Math.max(
    0,
    stageLeft(pi, ctx) - computeTransportBackQtyInContainersForMaterial(ctx, pi.materialItemId),
  )
}

export function computeLooseQtyStillAtEventForReturn(
  pi: ActivityPackItem,
  ctx: PackQuantityContext,
): number {
  if (!isPackReturnStage(ctx.stage)) return stageLeft(pi, ctx)
  if (ctx.stage === 'transport_back_returned' && ctx.profile === 'logistics') {
    return computeLooseQtyStillOnTransportBackForReturn(pi, ctx)
  }
  if (ctx.packContainers.length === 0) return stageLeft(pi, ctx)
  return Math.max(
    0,
    stageLeft(pi, ctx) - computeContainerStillAtEventQtyForMaterial(ctx, pi.materialItemId),
  )
}

export function computeEffectiveStageLeftQty(
  pi: ActivityPackItem,
  ctx: PackQuantityEffectiveLeftContext,
): number {
  if (isPackUnpackStage(ctx.stage)) {
    const acct = ctx.retourAccountingForUnpackLoose(pi)
    return Math.max(0, acct.retourTotal - (pi.quantityStored ?? 0) - (pi.quantityWet ?? 0))
  }
  if (!isPackConfirmedStage(ctx.stage)) {
    return stageLeft(pi, ctx)
  }
  const raw = stageLeft(pi, ctx)
  if (isPhysicalComboAsSet(pi, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) return raw
  const shells = ctx.packContainerBatchCountByMaterialItemId[pi.materialItemId] ?? 0
  if (shells <= 0) return raw
  return Math.max(0, raw - Math.min(shells, raw))
}

export function computeConsumableQtyAlreadyBeyondCurrentStage(
  pi: ActivityPackItem,
  stage: PackStage,
): boolean {
  if (stage === 'confirmed_packed') {
    return (pi.quantityPacked ?? 0) > 0
  }
  if (stage === 'packed_transport_to') {
    return (
      (pi.quantityTransportTo ?? 0) > 0 ||
      (pi.quantityIssued ?? 0) > 0 ||
      (pi.quantityTransportBack ?? 0) > 0
    )
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    return (pi.quantityTransportBack ?? 0) > 0 || (pi.quantityReturned ?? 0) > 0
  }
  if (stage === 'at_event_transport_back') {
    return (pi.quantityReturned ?? 0) > 0
  }
  return false
}

export function computePackIssueForwardMax(
  pi: ActivityPackItem,
  ctx: PackQuantityForwardMaxContext,
): number {
  if (isPackForwardToEventStage(ctx.stage) || ctx.stage === 'at_event_transport_back') {
    if (ctx.isCrateShellPackItem(pi)) {
      return stageLeft(pi, ctx)
    }
    return Math.min(computeLooseQtyForPackItem(pi, ctx), stageLeft(pi, ctx))
  }
  if (isPackReturnStage(ctx.stage)) {
    if (ctx.stage === 'transport_back_returned' && ctx.profile === 'logistics') {
      return Math.max(
        ctx.consumablePhysicalReturnMax(pi),
        computeLooseQtyStillOnTransportBackForReturn(pi, ctx),
      )
    }
    return ctx.consumablePhysicalReturnMax(pi)
  }
  if (isPackUnpackStage(ctx.stage)) {
    return ctx.pendingStoreLooseQtyForPackItem(pi)
  }
  if (isPackConfirmedStage(ctx.stage) && ctx.isCrateShellPackItem(pi)) {
    return stageLeft(pi, ctx)
  }
  return computeEffectiveStageLeftQty(pi, ctx)
}

export function computeRightQtyForMoveBack(
  pi: ActivityPackItem,
  ctx: PackQuantityMoveBackContext,
): number {
  if (isPackUnpackStage(ctx.stage)) {
    return ctx.storedLooseQtyForPackItem(pi)
  }
  if (isPackForwardToEventStage(ctx.stage)) {
    if (ctx.isCrateShellPackItem(pi)) {
      return stageRight(pi, ctx)
    }
    return computeLooseQtyOnRightMirror(pi, ctx)
  }
  if (ctx.stage === 'at_event_transport_back') {
    return computeLooseTransportBackOnRight(pi, ctx)
  }
  if (isPackReturnStage(ctx.stage)) {
    const looseRet = ctx.returnedLooseQtyForPackItem(pi)
    if (looseRet > 0) return looseRet
  }
  return stageRight(pi, ctx)
}

export function computeContainerShellIssueableUnits(
  containerId: string,
  ctx: PackQuantityContext,
): number {
  const shell = ctx.shellPackItemForContainer(containerId)
  if (!shell) return 0
  return computePackItemRemainingAtForwardStage(shell, ctx.stage)
}

export function computeContainerShellTakeMax(
  containerId: string,
  ctx: PackQuantityContext,
  hasProgressOnRight: boolean,
): number {
  if (!isPackForwardToEventStage(ctx.stage)) return 0
  if (hasProgressOnRight) return 0
  const c = ctx.packContainers.find((x) => x.id === containerId)
  if (!c?.container_batch_id && !c?.container_material_item_id) return 0
  const shellRem = computeContainerShellIssueableUnits(containerId, ctx)
  if (shellRem > 0) return shellRem
  const shell = ctx.shellPackItemForContainer(containerId)
  if (!shell) return 0
  if (ctx.stage === 'packed_transport_to') {
    if ((shell.quantityTransportTo ?? 0) > 0) return 0
  } else if ((shell.quantityIssued ?? 0) > 0) {
    return 0
  }
  return 1
}
