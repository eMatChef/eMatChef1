/**
 * Einheitliche Pack-Workflow-Regeln — siehe docs/activities/pack-workflow-rules.md
 *
 * Eine Matrix für Tabs, Placement, Listen-Filter und Sonderregeln.
 */
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateCheckLeg } from '@/components/activities/packCrateCheckLeg'
import { packCrateCheckLegForStage } from '@/components/activities/packCrateCheckLeg'
import {
  isPhysicalComboPackItem,
  isVirtualComboPackItem,
} from '@/components/activities/packMaterialDisplay'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import {
  isCrateShellPackItem,
  isPhysicalComboAsSet,
  packShellContainerForPackItem,
} from '@/components/activities/packShellCrateHelpers'
import {
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnOrUnpackWarehouseStage,
  isPackReturnPipelineStage,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
  PACK_STAGE_KEYS_LOGISTICS_MEMBER,
  PACK_STAGE_KEYS_LOGISTICS_MW,
  PACK_STAGE_KEYS_QUICK_MEMBER,
  PACK_STAGE_KEYS_QUICK_MW,
} from '@/components/activities/packStageQuantities'

export type PackWorkflowRole = 'mw_dc' | 'member'
export type PackMaterialKind = 'loose' | 'pack_crate' | 'physical_combo' | 'virtual_combo'
export type PackItemPlacement = 'category' | 'pack_crates'
export type PackCrateCheckMode = 'lightweight' | 'full'
export type PackReturnCrateMode = 'lightweight_modal' | 'full'
export type PackIssuesUi = 'row_buttons' | 'combo_header_modal'

/** Kontext für dynamische Mengen/Helfer aus ActivityPackListTab. */
export type PackWorkflowListContext = {
  stage: PackStage
  profile: PackWorkflowProfile
  showPackContainersUi: boolean
  packContainers: ActivityPackContainer[]
  virtualContainerIdByPackItemId?: Record<string, string>
  hasPackContainers: boolean
  effectiveStageLeftQty: (p: ActivityPackItem) => number
  getStageLeftQty: (p: ActivityPackItem) => number
  getStageRightQty: (p: ActivityPackItem) => number
  looseQtyForPackItem: (p: ActivityPackItem) => number
  consumableShowsZeroOnStageLeft: (p: ActivityPackItem) => boolean
  consumableConsumptionRemaining: (p: ActivityPackItem) => number
  consumablePhysicalReturnMax: (p: ActivityPackItem) => number
  looseQtyStillAtEventForReturn: (p: ActivityPackItem) => number
  pendingStoreLooseQtyForPackItem: (p: ActivityPackItem) => number
  returnedLooseQtyForPackItem: (p: ActivityPackItem) => number
  storedLooseQtyForPackItem: (p: ActivityPackItem) => number
  storedShellLooseQtyForPackItem: (p: ActivityPackItem) => number
  looseQtyOnRightMirror: (p: ActivityPackItem) => number
  looseTransportBackOnRight: (p: ActivityPackItem) => number
  notTakenQtyForReturn: (p: ActivityPackItem) => number
  notTakenToEventQtyForMaterial: (materialItemId: string) => number
  consumableStillOnlyInCrateAtReturn: (p: ActivityPackItem) => boolean
  consumableBookedConsumptionQty: (p: ActivityPackItem) => number
  isIndividuallyStorableCrateShell: (p: ActivityPackItem) => boolean
  containerReturnedAsWhole: (containerId: string) => boolean
  qtyInContainersForItem: (p: ActivityPackItem) => number
  issuedQtyInContainersForMaterial: (materialItemId: string) => number
  transportToQtyInContainersForMaterial: (materialItemId: string) => number
  transportBackQtyInContainersForMaterial: (materialItemId: string) => number
  isConsumablePackLine: (p: ActivityPackItem) => boolean
}

export type PackWorkflowContainerContext = PackWorkflowListContext & {
  stageLeftItemIds: Set<string>
  getLeftQtyForMerge?: (p: ActivityPackItem) => number
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  containerHasPackedContent: (containerId: string) => boolean
  containerHasIssuedAtEvent?: (containerId: string) => boolean
}

function isPackContainerMergedForVisibleList(
  c: ActivityPackContainer,
  ctx: PackWorkflowContainerContext,
  stageLeftItems: ActivityPackItem[],
): boolean {
  if (isPackForwardToEventStage(ctx.stage) && ctx.containerHasIssuedAtEvent?.(c.id)) {
    return false
  }
  return isPackContainerMergedIntoStageLeftRow(
    c,
    ctx.packContainers,
    stageLeftItems,
    ctx.stage,
    ctx.getLeftQtyForMerge,
  )
}

// ─── Profil & Rolle ─────────────────────────────────────────────────────────

export function packWorkflowRole(canManageMaterials: boolean): PackWorkflowRole {
  return canManageMaterials ? 'mw_dc' : 'member'
}

export function packWorkflowTabs(
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
): PackStage[] {
  const role = packWorkflowRole(canManageMaterials)
  if (profile === 'quick' || profile === 'external') {
    return role === 'mw_dc' ? [...PACK_STAGE_KEYS_QUICK_MW] : [...PACK_STAGE_KEYS_QUICK_MEMBER]
  }
  if (profile === 'logistics') {
    return role === 'mw_dc' ? [...PACK_STAGE_KEYS_LOGISTICS_MW] : [...PACK_STAGE_KEYS_LOGISTICS_MEMBER]
  }
  return []
}

export function packWorkflowCanEdit(
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
  _activityStatus: string,
): boolean {
  if (profile === 'external' && !canManageMaterials) return false
  return true
}

export function packShowContainersUi(profile: PackWorkflowProfile, stage: PackStage): boolean {
  if (profile === 'quick' || profile === 'external') {
    return (
      stage === 'confirmed_packed' ||
      stage === 'packed_at_event' ||
      stage === 'at_event_returned' ||
      stage === 'returned_unpack'
    )
  }
  return (
    stage === 'confirmed_packed' ||
    stage === 'packed_transport_to' ||
    stage === 'transport_to_at_event' ||
    stage === 'at_event_transport_back' ||
    stage === 'transport_back_returned' ||
    stage === 'returned_unpack'
  )
}

// ─── Materialarten & Placement ──────────────────────────────────────────────

export function resolvePackMaterialKind(
  item: ActivityPackItem,
  packContainers: ActivityPackContainer[],
): PackMaterialKind {
  if (isVirtualComboPackItem(item)) return 'virtual_combo'
  if (isPhysicalComboPackItem(item)) return 'physical_combo'
  if (isCrateShellPackItem(item, packContainers)) return 'pack_crate'
  return 'loose'
}

export function packItemPlacement(kind: PackMaterialKind): PackItemPlacement {
  return kind === 'pack_crate' ? 'pack_crates' : 'category'
}

export function isLooseCategoryPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  virtualContainerIdByPackItemId?: Record<string, string>,
): boolean {
  return !isCrateShellPackItem(pi, packContainers, virtualContainerIdByPackItemId)
}

// ─── Shell / Kisten Placement (aus packShellCrateHelpers migriert) ───────────

export function isOrphanShellWithoutPackContainer(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  activePackStage: string,
): boolean {
  if (activePackStage !== 'confirmed_packed') return false
  if (isPhysicalComboAsSet(pi, packContainers)) return false
  if (!isCrateShellPackItem(pi, packContainers)) return false
  if (packShellContainerForPackItem(pi, packContainers) != null) return false
  if ((pi.linkedContainerBatchId ?? '').trim() !== '') return false
  return true
}

export function crateShellExcludedFromLooseForwardList(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  isForwardToEventStage: boolean,
  virtualContainerIdByPackItemId?: Record<string, string>,
  activePackStage?: string,
): boolean {
  if (!isForwardToEventStage) return false
  if (activePackStage === 'packed_transport_to') return false
  if (!isCrateShellPackItem(pi, packContainers, virtualContainerIdByPackItemId)) return false
  return packShellContainerForPackItem(pi, packContainers, virtualContainerIdByPackItemId) != null
}

export function hideShellPackItemOnConfirmedPackedLeft(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  activePackStage: string,
  showPackContainersUi: boolean,
): boolean {
  if (!showPackContainersUi || activePackStage !== 'confirmed_packed') return false
  if (!isCrateShellPackItem(pi, packContainers)) return false
  const leftRest = Math.max(0, (pi.quantityOrdered ?? 0) - (pi.quantityPacked ?? 0))
  if (leftRest > 0) return false
  return packShellContainerForPackItem(pi, packContainers) != null
}

export function packContainerVisibleOnConfirmedPackedRight(
  _containerId: string,
  shellPackItem: ActivityPackItem | undefined,
  containerHasPackedContent: boolean,
): boolean {
  if (shellPackItem && (shellPackItem.quantityPacked ?? 0) < 1) return false
  if (containerHasPackedContent) return true
  return (shellPackItem?.quantityPacked ?? 0) > 0
}

export function isPackContainerMergedIntoStageLeftRow(
  c: ActivityPackContainer,
  packContainers: ActivityPackContainer[],
  stageLeftItems: ActivityPackItem[],
  activePackStage: string,
  getLeftQty?: (p: ActivityPackItem) => number,
): boolean {
  if (
    activePackStage === 'packed_transport_to' ||
    activePackStage === 'transport_to_at_event'
  ) {
    return false
  }
  if (
    activePackStage !== 'packed_at_event' &&
    activePackStage !== 'packed_transport_to' &&
    activePackStage !== 'transport_to_at_event'
  ) {
    return false
  }
  const leftQty =
    getLeftQty ??
    ((p: ActivityPackItem) => Math.max(0, (p.quantityPacked ?? 0) - (p.quantityIssued ?? 0)))
  for (const p of stageLeftItems) {
    if (!isCrateShellPackItem(p, packContainers)) continue
    const shellC = packShellContainerForPackItem(p, packContainers)
    if (shellC?.id === c.id && leftQty(p) > 0) return true
  }
  return false
}

export function packContainerHiddenInCratesSection(opts: {
  container: ActivityPackContainer
  stage: PackStage
  stageLeftItemIds: Set<string>
  packContainers: ActivityPackContainer[]
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
}): boolean {
  if (!isPackForwardToEventStage(opts.stage)) return false
  const sh = opts.shellPackItemForContainer(opts.container.id)
  if (!sh || !isCrateShellPackItem(sh, opts.packContainers)) return false
  return opts.stageLeftItemIds.has(sh.id)
}

// ─── Listen-Filter: links (stageLeftItems) ──────────────────────────────────

export function shouldIncludePackItemOnStageLeft(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  const { stage, packContainers, virtualContainerIdByPackItemId } = ctx
  if (isOrphanShellWithoutPackContainer(p, packContainers, stage)) return false
  if (
    ctx.showPackContainersUi &&
    isPackForwardToEventStage(stage) &&
    crateShellExcludedFromLooseForwardList(
      p,
      packContainers,
      true,
      virtualContainerIdByPackItemId,
      stage,
    )
  ) {
    return false
  }
  if (
    hideShellPackItemOnConfirmedPackedLeft(p, packContainers, stage, ctx.showPackContainersUi)
  ) {
    return false
  }
  if (ctx.effectiveStageLeftQty(p) <= 0 && !ctx.consumableShowsZeroOnStageLeft(p)) return false
  if (
    isPackForwardToEventStage(stage) &&
    ctx.showPackContainersUi &&
    ctx.getStageLeftQty(p) > 0 &&
    ctx.looseQtyForPackItem(p) <= 0 &&
    !isCrateShellPackItem(p, packContainers, virtualContainerIdByPackItemId)
  ) {
    return false
  }
  if (isPackReturnStage(stage) && p.isConsumable) {
    if (ctx.consumableConsumptionRemaining(p) > 0) return false
    if (ctx.consumablePhysicalReturnMax(p) <= 0 && !ctx.consumableShowsZeroOnStageLeft(p)) {
      return false
    }
  }
  if (isPackReturnStage(stage) && ctx.hasPackContainers && ctx.getStageLeftQty(p) > 0) {
    if (ctx.looseQtyStillAtEventForReturn(p) <= 0) return false
  }
  if (isPackUnpackStage(stage) && ctx.hasPackContainers && ctx.getStageLeftQty(p) > 0) {
    if (ctx.pendingStoreLooseQtyForPackItem(p) <= 0) return false
  }
  if (
    isPackUnpackStage(stage) &&
    ctx.showPackContainersUi &&
    isCrateShellPackItem(p, packContainers, virtualContainerIdByPackItemId) &&
    packShellContainerForPackItem(p, packContainers, virtualContainerIdByPackItemId) != null
  ) {
    return false
  }
  return true
}

/** Shell-Zeile in Kategorie-Gruppe (PackCrateShellPackItemRow). */
export function shouldShowPackItemAsCategoryShellRow(
  p: ActivityPackItem,
  ctx: Pick<PackWorkflowListContext, 'stage' | 'packContainers'>,
): boolean {
  return (
    isCrateShellPackItem(p, ctx.packContainers) &&
    !isOrphanShellWithoutPackContainer(p, ctx.packContainers, ctx.stage)
  )
}

// ─── Listen-Filter: rechts / Spiegel ────────────────────────────────────────

export function shouldIncludePackItemOnRightLooseMirror(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!isLooseCategoryPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    return false
  }
  if (ctx.stage === 'at_event_transport_back') {
    return ctx.looseTransportBackOnRight(p) > 0
  }
  if (isPackForwardToEventStage(ctx.stage)) {
    return ctx.looseQtyOnRightMirror(p) > 0
  }
  return false
}

export function shouldIncludePackItemOnReturnedLoose(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!isPackReturnStage(ctx.stage)) return false
  if (isOrphanShellWithoutPackContainer(p, ctx.packContainers, ctx.stage)) return false
  if (isCrateShellPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    const shellContainer = packShellContainerForPackItem(
      p,
      ctx.packContainers,
      ctx.virtualContainerIdByPackItemId,
    )
    if (shellContainer && ctx.containerReturnedAsWhole(shellContainer.id)) return false
    if (ctx.isIndividuallyStorableCrateShell(p)) {
      return (p.quantityReturned ?? 0) > 0
    }
    return false
  }
  return ctx.returnedLooseQtyForPackItem(p) > 0
}

export function shouldIncludePackItemOnStoredLoose(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!isPackUnpackStage(ctx.stage)) return false
  if (isOrphanShellWithoutPackContainer(p, ctx.packContainers, ctx.stage)) return false
  if (ctx.storedShellLooseQtyForPackItem(p) > 0) return true
  if (isCrateShellPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    return false
  }
  return ctx.storedLooseQtyForPackItem(p) > 0
}

export function shouldIncludePackItemOnReturnNotTaken(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!isPackReturnOrUnpackWarehouseStage(ctx.stage)) return false
  if (isOrphanShellWithoutPackContainer(p, ctx.packContainers, ctx.stage)) return false
  if (isCrateShellPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    return false
  }
  if (
    isPackUnpackStage(ctx.stage) &&
    (ctx.pendingStoreLooseQtyForPackItem(p) > 0 || (p.quantityReturned ?? 0) > 0)
  ) {
    return false
  }
  if (ctx.notTakenQtyForReturn(p) > 0) return true
  return ctx.notTakenToEventQtyForMaterial(p.materialItemId) > 0
}

export function shouldIncludePackItemOnConsumableOverview(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!isPackReturnOrUnpackWarehouseStage(ctx.stage)) return false
  if (!ctx.isConsumablePackLine(p)) return false
  if (ctx.consumableStillOnlyInCrateAtReturn(p)) return false
  if (ctx.consumableConsumptionRemaining(p) > 0) return true
  if ((p.quantityReturned ?? 0) > 0 || (p.quantityStored ?? 0) > 0) return true
  if (ctx.consumableBookedConsumptionQty(p) > 0 && ctx.consumableConsumptionRemaining(p) <= 0) {
    return false
  }
  return ctx.consumableBookedConsumptionQty(p) > 0
}

export function shouldIncludePackItemOnReturnConsumed(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!isPackReturnOrUnpackWarehouseStage(ctx.stage)) return false
  if (!p.isConsumable) return false
  if (isOrphanShellWithoutPackContainer(p, ctx.packContainers, ctx.stage)) return false
  if (isCrateShellPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    return false
  }
  return ctx.consumableBookedConsumptionQty(p) > 0
}

/** «Ohne Behälter» — nur lose Menge ohne Kistenanteil. */
export function shouldIncludePackItemInLooseOnlyGroup(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!ctx.showPackContainersUi) return false
  if (!isLooseCategoryPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    return false
  }
  const stage = ctx.stage
  if (isPackConfirmedStage(stage)) {
    return (
      ctx.getStageRightQty(p) > 0 && ctx.qtyInContainersForItem(p) === 0
    )
  }
  if (isPackForwardToEventStage(stage)) {
    if (stage === 'packed_transport_to') {
      return (
        ctx.looseQtyOnRightMirror(p) > 0 &&
        ctx.transportToQtyInContainersForMaterial(p.materialItemId) === 0
      )
    }
    return (
      ctx.looseQtyOnRightMirror(p) > 0 &&
      ctx.issuedQtyInContainersForMaterial(p.materialItemId) === 0
    )
  }
  if (stage === 'at_event_transport_back') {
    return (
      ctx.looseTransportBackOnRight(p) > 0 &&
      ctx.transportBackQtyInContainersForMaterial(p.materialItemId) === 0
    )
  }
  return false
}

/** Teilweise lose, teils in Behälter. */
export function shouldIncludePackItemInLoosePartialGroup(
  p: ActivityPackItem,
  ctx: PackWorkflowListContext,
): boolean {
  if (!ctx.showPackContainersUi) return false
  if (!isLooseCategoryPackItem(p, ctx.packContainers, ctx.virtualContainerIdByPackItemId)) {
    return false
  }
  const stage = ctx.stage
  if (isPackConfirmedStage(stage)) {
    return ctx.looseQtyForPackItem(p) > 0 && ctx.qtyInContainersForItem(p) > 0
  }
  if (isPackForwardToEventStage(stage)) {
    if (stage === 'packed_transport_to') {
      return (
        ctx.looseQtyOnRightMirror(p) > 0 &&
        ctx.transportToQtyInContainersForMaterial(p.materialItemId) > 0
      )
    }
    return (
      ctx.looseQtyOnRightMirror(p) > 0 &&
      ctx.issuedQtyInContainersForMaterial(p.materialItemId) > 0
    )
  }
  if (stage === 'at_event_transport_back') {
    return (
      ctx.looseTransportBackOnRight(p) > 0 &&
      ctx.transportBackQtyInContainersForMaterial(p.materialItemId) > 0
    )
  }
  return false
}

// ─── Listen-Filter: Packkisten ───────────────────────────────────────────────

export function shouldShowPackContainerInWarehouseVisibleList(
  c: ActivityPackContainer,
  ctx: PackWorkflowContainerContext,
  stageLeftItems: ActivityPackItem[],
): boolean {
  if (isPackContainerMergedForVisibleList(c, ctx, stageLeftItems)) {
    return false
  }
  return !packContainerHiddenInCratesSection({
    container: c,
    stage: ctx.stage,
    stageLeftItemIds: ctx.stageLeftItemIds,
    packContainers: ctx.packContainers,
    shellPackItemForContainer: ctx.shellPackItemForContainer,
  })
}

export function shouldShowPackContainerOnConfirmedPackedRight(
  c: ActivityPackContainer,
  ctx: PackWorkflowContainerContext,
  stageLeftItems: ActivityPackItem[],
): boolean {
  if (isPackContainerMergedForVisibleList(c, ctx, stageLeftItems)) {
    return false
  }
  return packContainerVisibleOnConfirmedPackedRight(
    c.id,
    ctx.shellPackItemForContainer(c.id),
    ctx.containerHasPackedContent(c.id),
  )
}

// ─── Kistencheck, Issues, Ausgepackt ────────────────────────────────────────

export function packCrateCheckMode(
  role: PackWorkflowRole,
  leg: PackCrateCheckLeg | null,
): PackCrateCheckMode | null {
  if (!leg) return null
  if (role === 'mw_dc') return 'full'
  if (leg === 'return' || leg === 'outbound') return 'lightweight'
  return null
}

export function packCrateCheckModeForStage(
  role: PackWorkflowRole,
  stage: PackStage,
): PackCrateCheckMode | null {
  return packCrateCheckMode(role, packCrateCheckLegForStage(stage))
}

export function packReturnCrateMode(role: PackWorkflowRole): PackReturnCrateMode {
  return role === 'member' ? 'lightweight_modal' : 'full'
}

export function packIssuesVisibleForStage(stage: PackStage): boolean {
  return (
    stage === 'transport_to_at_event' ||
    stage === 'packed_at_event' ||
    isPackReturnPipelineStage(stage)
  )
}

export function packIssuesUiForKind(kind: PackMaterialKind): PackIssuesUi {
  return kind === 'physical_combo' ? 'combo_header_modal' : 'row_buttons'
}

export function packStorePhysComboMode(role: PackWorkflowRole): 'checklist' | null {
  return role === 'mw_dc' ? 'checklist' : null
}

export function packStoreWarningForOpenIssues(hasOpenRepairOrLoss: boolean): boolean {
  return hasOpenRepairOrLoss
}

export function packUnpackContainerSortKey(
  containerId: string,
  opts: {
    isPhysicalComboContainer: (id: string) => boolean
    containerPendingInnerUnits: (id: string) => number
    containerPendingShellOnly: (id: string) => boolean
  },
): number {
  if (opts.isPhysicalComboContainer(containerId)) return 0
  if (opts.containerPendingInnerUnits(containerId) > 0) return 1
  if (opts.containerPendingShellOnly(containerId)) return 2
  return 1
}

export function packCrateCheckRequestLightweight(
  role: PackWorkflowRole,
  leg: PackCrateCheckLeg | null,
): boolean {
  return packCrateCheckMode(role, leg) === 'lightweight'
}

/** Linkes Panel: Kisten-Sektion sichtbar? */
export function packLeftPanelShowsCratesSection(
  stage: PackStage,
  showPackContainersUi: boolean,
  crateCount: number,
): boolean {
  if (!showPackContainersUi || crateCount <= 0) return false
  return (
    isPackForwardToEventStage(stage) ||
    isPackReturnPipelineStage(stage) ||
    isPackUnpackStage(stage)
  )
}
