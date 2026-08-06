import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  isPhysicalComboPackItem,
  isVirtualComboPackItem,
  packMaterialDisplayName,
  packRackLabel,
} from '@/components/activities/packMaterialDisplay'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import {
  materialJourneyShelfForContainer,
  materialJourneyShelfKey,
  materialJourneyShelfLabel,
} from '@/components/activities/materialJourneyRegalGroups'
import { packItemsForMaterialJourney } from '@/components/activities/materialJourneyJsSummary'
import {
  isVirtualComboTogetherContainer,
  shouldIncludePackItemOnReturnNotTaken,
  shouldIncludePackItemOnStageLeft,
  shouldIncludePackItemOnStoredLoose,
  shouldShowContainerOnRightMirror,
  shouldShowContainerOnStageLeft,
  shouldShowPackContainerInWarehouseVisibleList,
  shouldShowPackContainerOnConfirmedPackedRight,
  type PackWorkflowContainerContext,
  type PackWorkflowListContext,
} from '@/components/activities/packWorkflowRules'
import {
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackUnpackStage,
} from '@/components/activities/packStageQuantities'
import { isCrateShellPackItem, packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
import { comboComponentsForJourneyContainer } from '@/composables/useMaterialJourneyCrateSections'
import type { ComboComponent } from '@/api/materials'
import type { PackStage } from '@/components/activities/packStageQuantities'

/** Packkiste als Karten-Zeile (nicht lose Shell) — v. a. Transport hin. */
function shouldShowPackContainerInJourneyList(
  container: ActivityPackContainer,
  ctx: MaterialJourneyTaskBuildContext,
): boolean {
  if (
    shouldShowPackContainerInWarehouseVisibleList(
      container,
      ctx.containerCtx,
      ctx.stageLeftItems,
    )
  ) {
    return true
  }
  if (!ctx.listCtx.showPackContainersUi) {
    return false
  }
  if (isPackForwardToEventStage(ctx.packStage)) {
    if (isVirtualComboTogetherContainer(container)) {
      return shouldShowContainerOnStageLeft(container.id, ctx.containerCtx)
    }
    const shell = ctx.shellPackItemForContainer(container.id)
    if (!shell || !isCrateShellPackItem(shell, ctx.packContainers)) return false
    if (!ctx.stageLeftItems.some((pi) => pi.id === shell.id)) return false
    return shouldShowContainerOnStageLeft(container.id, ctx.containerCtx)
  }
  if (isPackReturnStage(ctx.packStage) || isPackUnpackStage(ctx.packStage)) {
    return shouldShowContainerOnStageLeft(container.id, ctx.containerCtx)
  }
  return false
}

/** Stock-Kind einer virt. Kombo together — nur im Set-Sheet, nicht als lose Zeile. */
function isPackItemInVirtualTogetherContainer(
  pi: ActivityPackItem,
  ctx: Pick<MaterialJourneyTaskBuildContext, 'packContainers' | 'containerCtx'>,
): boolean {
  const mid = (pi.materialItemId ?? '').trim()
  if (!mid) return false
  for (const c of ctx.packContainers) {
    if (!isVirtualComboTogetherContainer(c)) continue
    const items = ctx.containerCtx.containerItemsForContainer?.(c.id) ?? []
    if (items.some((ci) => ci.material_item_id === mid && (ci.quantity_packed ?? 0) > 0)) {
      return true
    }
  }
  return false
}

function shouldHideShellMaterialForJourneyCrateRow(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): boolean {
  if (!ctx.listCtx.showPackContainersUi) return false
  if (!isCrateShellPackItem(pi, ctx.packContainers)) return false

  const linkedContainers = ctx.packContainers.filter((c) => containerMatchesShellPackItem(c, pi))
  if (linkedContainers.length === 0) return false

  // Packen: Shell nur als Packkisten-Zeile, nie zusätzlich als lose Position.
  if (isPackConfirmedStage(ctx.packStage)) return true

  // Einlagern: Hülle nur über Packkiste (Seriennummer), nicht als lose Position.
  if (isPackUnpackStage(ctx.packStage)) {
    return linkedContainers.some((c) => shouldShowPackContainerInJourneyList(c, ctx))
  }

  if (!isPackForwardToEventStage(ctx.packStage)) return false
  return linkedContainers.some((c) => shouldShowPackContainerInJourneyList(c, ctx))
}

function containerMatchesShellPackItem(
  container: ActivityPackContainer,
  pi: ActivityPackItem,
): boolean {
  const batchId = (container.container_batch_id ?? '').trim()
  const linkBatch = (pi.linkedContainerBatchId ?? '').trim()
  if (batchId && linkBatch && batchId === linkBatch) return true
  const mid = (container.container_material_item_id ?? '').trim()
  return Boolean(mid && mid === pi.materialItemId)
}

/** Lose Zeile «Mit mir unterwegs»: nur wirklich lose Menge, nicht Kistenanteil. */
function journeyLooseDoneQty(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): number {
  const { packStage, listCtx } = ctx
  if (isPackForwardToEventStage(packStage)) {
    return listCtx.looseQtyOnRightMirror(pi)
  }
  if (packStage === 'at_event_transport_back') {
    return listCtx.looseTransportBackOnRight(pi)
  }
  return listCtx.getStageRightQty(pi)
}

function journeyLooseRowIsDone(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
  isOpen: boolean,
): boolean {
  if (isOpen) return false
  return journeyLooseDoneQty(pi, ctx) > 0
}

export type MaterialJourneyTaskKind = 'loose' | 'crate' | 'virtual_crate' | 'combo' | 'not_taken'

export type MaterialJourneyTaskBadge =
  | 'physical_combo'
  | 'consumable'
  | 'js'
  | 'crate'
  | 'virtual_crate'
  | 'pack_crate'
  | 'not_taken'

/** Phys. Packkiste oder logisches Set (virt. Kombo together). */
export function isMaterialJourneyCrateKind(kind: MaterialJourneyTaskKind): boolean {
  return kind === 'crate' || kind === 'virtual_crate'
}

export type MaterialJourneyTaskRow = {
  id: string
  kind: MaterialJourneyTaskKind
  packItem?: ActivityPackItem
  container?: ActivityPackContainer
  title: string
  subtitle: string | null
  openQty: number
  doneQty: number
  maxForwardQty: number
  isOpen: boolean
  isDone: boolean
  badges: MaterialJourneyTaskBadge[]
  canMove: boolean
  canMoveBack: boolean
  maxMoveBackQty: number
  canOpenSheet: boolean
  categoryName: string | null
  shelfLabel: string
  shelfKey: string
  packCrateHint: string | null
}

export type MaterialJourneyFilterTab = 'open' | 'done' | 'byShelf'

export type MaterialJourneyTaskBuildContext = {
  listCtx: PackWorkflowListContext
  containerCtx: PackWorkflowContainerContext
  stageLeftItems: ActivityPackItem[]
  packStage: PackStage
  packContainers: ActivityPackContainer[]
  maxForwardQty: (pi: ActivityPackItem) => number
  containerIssueableUnits: (containerId: string) => number
  containerActionableUnits: (containerId: string) => number
  containerContentActionableUnits: (containerId: string) => number
  canMoveItem: (pi: ActivityPackItem) => boolean
  canMoveBackItem?: (pi: ActivityPackItem) => boolean
  rightQtyForMoveBack?: (pi: ActivityPackItem) => number
  canOpenSheet: boolean
  formatCrateLineCount: (count: number) => string
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  cratePeekLineCount?: (
    container: ActivityPackContainer,
    shellPackItem?: ActivityPackItem,
  ) => number
  qtyInPackCrateForItem?: (pi: ActivityPackItem) => number
  packCrateLabelsForItem?: (pi: ActivityPackItem) => string[]
  formatPackCrateHint?: (labels: string[]) => string
  comboComponentsByMaterialId?: Record<string, ComboComponent[]>
  comboMaterialIdByContainerId?: Record<string, string>
  /** Regal/Fach in Zeilen-Untertitel (false ab Transport hin bis vor Retour). */
  showShelfLocation?: boolean
  /** Packen: noch lose Sets/Artikel offen (Packkisten folgen diesem Signal). */
  hasOpenLooseComboPackWork?: boolean
}

/** Offene Material-Positionen ohne Packkisten (lose + Phys.-Kombi). */
export function hasOpenLooseComboMaterialTasks(rows: MaterialJourneyTaskRow[]): boolean {
  return rows.some((row) => row.isOpen && !isMaterialJourneyCrateKind(row.kind))
}

export function countOpenLooseComboMaterialTasks(rows: MaterialJourneyTaskRow[]): number {
  return rows.filter((row) => row.isOpen && !isMaterialJourneyCrateKind(row.kind)).length
}

export function resolveDefaultMaterialJourneyFilterTab(options: {
  stepAccess: 'editable' | 'readonly_past' | 'readonly_future'
  openLooseComboCount: number
  doneCount: number
  /** Alle offenen Checklisten-Positionen (inkl. Kisten) — für Ausgabe/Retour. */
  totalOpenCount?: number
}): MaterialJourneyFilterTab {
  if (options.stepAccess === 'readonly_past') return 'done'
  if (options.stepAccess === 'readonly_future') return 'open'
  const openRemaining = options.totalOpenCount ?? options.openLooseComboCount
  if (openRemaining === 0 && options.doneCount > 0) return 'done'
  return 'open'
}

export type MaterialJourneyFilterVariant = 'default' | 'quickIssue'

export function materialJourneyFilterVariantForStep(
  profile: PackWorkflowProfile,
  journeyStep: JourneyStep,
): MaterialJourneyFilterVariant {
  if (profile === 'logistics') return 'default'
  return journeyStep === 'issue' ? 'quickIssue' : 'default'
}

function taskBadges(pi: ActivityPackItem, ctx: MaterialJourneyTaskBuildContext): MaterialJourneyTaskBadge[] {
  const badges: MaterialJourneyTaskBadge[] = []
  if (pi.isConsumable) badges.push('consumable')
  if (pi.isJsMaterial) badges.push('js')
  const labels = ctx.packCrateLabelsForItem?.(pi) ?? []
  if ((ctx.qtyInPackCrateForItem?.(pi) ?? 0) > 0 && labels.length === 0) {
    badges.push('pack_crate')
  }
  return badges
}

function packCrateHintForItem(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
  isDone: boolean,
  doneQty: number,
): string | null {
  const labels = ctx.packCrateLabelsForItem?.(pi) ?? []
  if (labels.length === 0 || !ctx.formatPackCrateHint) return null
  const inCrate = (ctx.qtyInPackCrateForItem?.(pi) ?? 0) > 0
  if (isDone || inCrate || doneQty > 0) return ctx.formatPackCrateHint(labels)
  return null
}

function buildSubtitleParts(parts: string[]): string | null {
  return parts.length > 0 ? parts.join(' · ') : null
}

export function buildMaterialJourneyLooseTask(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow {
  const maxForwardQty = ctx.maxForwardQty(pi)
  const openQty = isPackUnpackStage(ctx.packStage) ? maxForwardQty : ctx.listCtx.effectiveStageLeftQty(pi)
  const isOpen = shouldIncludePackItemOnStageLeft(pi, ctx.listCtx)
  let doneQty = journeyLooseDoneQty(pi, ctx)
  let isDone = journeyLooseRowIsDone(pi, ctx, isOpen)
  if (
    isPackUnpackStage(ctx.packStage) &&
    !isOpen &&
    shouldIncludePackItemOnStoredLoose(pi, ctx.listCtx)
  ) {
    isDone = true
    doneQty = Math.max(doneQty, ctx.listCtx.storedLooseQtyForPackItem(pi))
  }
  const canMove = ctx.canMoveItem(pi) && maxForwardQty > 0 && isOpen
  const maxMoveBackQty = ctx.rightQtyForMoveBack?.(pi) ?? 0
  const canMoveBack =
    Boolean(ctx.canMoveBackItem?.(pi)) && isDone && maxMoveBackQty > 0

  const subtitleParts: string[] = []
  if (ctx.showShelfLocation !== false && isOpen) {
    const rack = packRackLabel(pi)
    if (rack) subtitleParts.push(rack)
    if (pi.storageSlotName?.trim()) subtitleParts.push(pi.storageSlotName.trim())
    if (openQty > 0) subtitleParts.push(String(openQty))
  }

  const shelfLabel = materialJourneyShelfLabel(pi)
  const shelfKey = materialJourneyShelfKey(shelfLabel)

  return {
    id: pi.id,
    kind: 'loose',
    packItem: pi,
    title: packMaterialDisplayName(pi),
    subtitle: buildSubtitleParts(subtitleParts),
    openQty,
    doneQty,
    maxForwardQty,
    isOpen,
    isDone,
    badges: taskBadges(pi, ctx),
    canMove,
    canMoveBack,
    maxMoveBackQty,
    canOpenSheet: false,
    categoryName: pi.categoryName?.trim() || null,
    shelfLabel,
    shelfKey,
    packCrateHint: packCrateHintForItem(pi, ctx, isDone, doneQty),
  }
}

export function buildMaterialJourneyComboTask(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow {
  const loose = buildMaterialJourneyLooseTask(pi, ctx)
  return {
    ...loose,
    id: `combo-${pi.id}`,
    kind: 'combo',
    badges: [...loose.badges, 'physical_combo'],
    canMove: false,
    canOpenSheet: ctx.canOpenSheet && loose.isOpen && ctx.maxForwardQty(pi) > 0,
  }
}

export function buildMaterialJourneyCrateTask(
  container: ActivityPackContainer,
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow {
  const issueable = ctx.containerActionableUnits(container.id)
  const contentIssueable = ctx.containerContentActionableUnits(container.id)
  const onConfirmedPackedRight =
    ctx.packStage === 'confirmed_packed' &&
    shouldShowPackContainerOnConfirmedPackedRight(
      container,
      ctx.containerCtx,
      ctx.stageLeftItems,
    )
  let isOpen = onConfirmedPackedRight
    ? false
    : shouldShowPackContainerInJourneyList(container, ctx)
  let isDone =
    onConfirmedPackedRight ||
    (!isOpen && shouldShowContainerOnRightMirror(container.id, ctx.containerCtx))

  // Packen: Kiste immer sichtbar — «offen» solange noch lose/Kombi-Material offen ist.
  if (isPackConfirmedStage(ctx.packStage) && ctx.listCtx.showPackContainersUi) {
    const hasLooseOpen = ctx.hasOpenLooseComboPackWork ?? false
    isOpen = hasLooseOpen
    isDone = !hasLooseOpen
  }

  // Einlagern: offen solange Inhalt oder Kistenhülle noch nicht eingelagert.
  if (isPackUnpackStage(ctx.packStage)) {
    isOpen = issueable > 0
    isDone = issueable <= 0
  }

  // Packen: Inhalt-Menge für Anzeige; Transport/sonst: Kiste als Ganzes (1 Shell).
  const usePackContentQty =
    isPackConfirmedStage(ctx.packStage) && ctx.listCtx.showPackContainersUi
  const forwardUnits =
    usePackContentQty && contentIssueable > 0 ? contentIssueable : issueable
  const displayQty = forwardUnits > 0 ? forwardUnits : isOpen ? Math.max(1, issueable) : 0
  const openQty = displayQty
  const doneQty = isDone ? 1 : 0
  const shellPackItem = ctx.shellPackItemForContainer(container.id)
  let lineCount = ctx.cratePeekLineCount
    ? ctx.cratePeekLineCount(container, shellPackItem)
    : (ctx.containerCtx.containerItemsForContainer?.(container.id) ?? []).length
  if (lineCount < 1) {
    const combo = comboComponentsForJourneyContainer(container, shellPackItem, {
      comboComponentsByMaterialId: ctx.comboComponentsByMaterialId ?? {},
      comboMaterialIdByContainerId: ctx.comboMaterialIdByContainerId,
    })
    if (combo.length > 0) lineCount = combo.length
  }
  const subtitleParts: string[] = []
  if (lineCount > 0) {
    subtitleParts.push(ctx.formatCrateLineCount(lineCount))
  } else if ((shellPackItem?.linkedContainerLabel ?? '').trim()) {
    subtitleParts.push((shellPackItem?.linkedContainerLabel ?? '').trim())
  } else {
    subtitleParts.push(ctx.formatCrateLineCount(0))
  }
  const { shelfLabel, shelfKey } = materialJourneyShelfForContainer(
    container,
    ctx.shellPackItemForContainer,
  )
  const maxMoveBackQty =
    shellPackItem && ctx.rightQtyForMoveBack
      ? Math.max(0, ctx.rightQtyForMoveBack(shellPackItem))
      : isDone
        ? 1
        : 0
  const canMoveBack =
    isDone &&
    shellPackItem != null &&
    maxMoveBackQty > 0 &&
    Boolean(ctx.canMoveBackItem?.(shellPackItem))

  const isVirtualSet = isVirtualComboTogetherContainer(container)
  const kind: MaterialJourneyTaskKind = isVirtualSet ? 'virtual_crate' : 'crate'
  const badges: MaterialJourneyTaskBadge[] = isVirtualSet ? ['virtual_crate'] : ['crate']

  return {
    id: `crate-${container.id}`,
    kind,
    container,
    packItem: shellPackItem ?? undefined,
    title: container.label,
    subtitle: buildSubtitleParts(subtitleParts),
    openQty,
    doneQty,
    maxForwardQty: forwardUnits > 0 ? forwardUnits : issueable,
    isOpen,
    isDone,
    badges,
    canMove: false,
    canMoveBack,
    maxMoveBackQty,
    canOpenSheet: ctx.canOpenSheet && isOpen,
    categoryName: null,
    shelfLabel,
    shelfKey,
    packCrateHint: null,
  }
}

export function buildMaterialJourneyTasks(
  packItems: ActivityPackItem[],
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow[] {
  const journeyItems = packItemsForMaterialJourney(packItems)
  const crateBaseCtx: MaterialJourneyTaskBuildContext = { ...ctx }

  const looseRows = journeyItems
    .filter((pi) => !isVirtualComboPackItem(pi) && !isPhysicalComboPackItem(pi))
    .filter((pi) => !shouldHideShellMaterialForJourneyCrateRow(pi, crateBaseCtx))
    .filter((pi) => !isPackItemInVirtualTogetherContainer(pi, crateBaseCtx))
    .map((pi) => buildMaterialJourneyLooseTask(pi, crateBaseCtx))
    .filter((row) => row.isOpen || row.isDone)

  const comboRows = journeyItems
    .filter((pi) => isPhysicalComboPackItem(pi))
    .filter((pi) => !shouldHideShellMaterialForJourneyCrateRow(pi, crateBaseCtx))
    .map((pi) => buildMaterialJourneyComboTask(pi, crateBaseCtx))
    .filter((row) => row.isOpen || row.isDone)

  const hasOpenLooseComboPackWork =
    looseRows.some((row) => row.isOpen) || comboRows.some((row) => row.isOpen)
  const crateCtx: MaterialJourneyTaskBuildContext = {
    ...crateBaseCtx,
    hasOpenLooseComboPackWork,
  }

  const crateRows =
    ctx.listCtx.showPackContainersUi
      ? ctx.packContainers
          .map((c) => buildMaterialJourneyCrateTask(c, crateCtx))
          .filter((row) => row.isOpen || row.isDone)
      : []

  const notTakenRows = journeyItems
    .filter((pi) => !isVirtualComboPackItem(pi) && !isPhysicalComboPackItem(pi))
    .filter((pi) => shouldIncludePackItemOnReturnNotTaken(pi, ctx.listCtx))
    .map((pi) => buildMaterialJourneyNotTakenTask(pi, ctx))

  return [...crateRows, ...comboRows, ...looseRows, ...notTakenRows]
}

function buildMaterialJourneyNotTakenTask(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow {
  const fromPipeline = ctx.listCtx.notTakenQtyForReturn(pi)
  const fromIssues = ctx.listCtx.notTakenToEventQtyForMaterial(pi.materialItemId)
  const qty = Math.max(fromPipeline, fromIssues)
  const shelfLabel = materialJourneyShelfLabel(pi)
  return {
    id: `not-taken-${pi.id}`,
    kind: 'not_taken',
    packItem: pi,
    title: packMaterialDisplayName(pi),
    subtitle: null,
    openQty: 0,
    doneQty: qty,
    maxForwardQty: 0,
    isOpen: false,
    isDone: true,
    badges: ['not_taken'],
    canMove: false,
    canMoveBack: false,
    maxMoveBackQty: 0,
    canOpenSheet: false,
    categoryName: pi.categoryName ?? null,
    shelfLabel,
    shelfKey: materialJourneyShelfKey(shelfLabel),
    packCrateHint: null,
  }
}

/** Am Anlass (Logistics): statische Bestandsliste — nur quantity_issued > 0. */
export function buildMaterialJourneyAtEventInventory(
  packItems: ActivityPackItem[],
  ctx: Pick<
    MaterialJourneyTaskBuildContext,
    'packContainers' | 'shellPackItemForContainer' | 'formatCrateLineCount' | 'cratePeekLineCount'
  > & {
    containerItemsByContainerId?: Record<string, { material_item_id: string; quantity_packed?: number; quantity_issued?: number }[]>
  },
): MaterialJourneyTaskRow[] {
  const journeyItems = packItemsForMaterialJourney(packItems)
  const itemsByContainer = ctx.containerItemsByContainerId ?? {}

  function materialInVirtualTogether(mid: string): boolean {
    for (const c of ctx.packContainers) {
      if (!isVirtualComboTogetherContainer(c)) continue
      const items = itemsByContainer[c.id] ?? []
      if (items.some((ci) => ci.material_item_id === mid && (ci.quantity_packed ?? 0) > 0)) {
        return true
      }
    }
    return false
  }

  function virtualTogetherHasIssued(containerId: string): boolean {
    return (itemsByContainer[containerId] ?? []).some((ci) => (ci.quantity_issued ?? 0) > 0)
  }

  const looseRows = journeyItems
    .filter((pi) => !isVirtualComboPackItem(pi) && !isPhysicalComboPackItem(pi))
    .filter((pi) => (pi.quantityIssued ?? 0) > 0)
    .filter((pi) => !materialInVirtualTogether(pi.materialItemId))
    .map((pi) => {
      const issued = pi.quantityIssued ?? 0
      const shelfLabel = materialJourneyShelfLabel(pi)
      const shelfKey = materialJourneyShelfKey(shelfLabel)
      const badges: MaterialJourneyTaskBadge[] = pi.isConsumable ? ['consumable'] : []
      return {
        id: `inv-loose-${pi.id}`,
        kind: 'loose' as const,
        packItem: pi,
        title: packMaterialDisplayName(pi),
        subtitle: null,
        openQty: 0,
        doneQty: issued,
        maxForwardQty: 0,
        isOpen: false,
        isDone: true,
        badges,
        canMove: false,
        canMoveBack: false,
        maxMoveBackQty: 0,
        canOpenSheet: false,
        categoryName: pi.categoryName ?? null,
        shelfLabel,
        shelfKey,
        packCrateHint: null,
      }
    })

  const comboRows = journeyItems
    .filter((pi) => isPhysicalComboPackItem(pi))
    .filter((pi) => (pi.quantityIssued ?? 0) > 0)
    .map((pi) => {
      const issued = pi.quantityIssued ?? 0
      const shelfLabel = materialJourneyShelfLabel(pi)
      return {
        id: `inv-combo-${pi.id}`,
        kind: 'combo' as const,
        packItem: pi,
        title: packMaterialDisplayName(pi),
        subtitle: null,
        openQty: 0,
        doneQty: issued,
        maxForwardQty: 0,
        isOpen: false,
        isDone: true,
        badges: ['physical_combo'] as MaterialJourneyTaskBadge[],
        canMove: false,
        canMoveBack: false,
        maxMoveBackQty: 0,
        canOpenSheet: false,
        categoryName: pi.categoryName ?? null,
        shelfLabel,
        shelfKey: materialJourneyShelfKey(shelfLabel),
        packCrateHint: null,
      }
    })

  const crateRows = ctx.packContainers
    .filter((container) => {
      const shell = ctx.shellPackItemForContainer(container.id)
      if ((shell?.quantityIssued ?? 0) > 0) return true
      if (isVirtualComboTogetherContainer(container) && virtualTogetherHasIssued(container.id)) {
        return true
      }
      return false
    })
    .map((container) => {
      const shellPackItem = ctx.shellPackItemForContainer(container.id)
      const isVirtualSet = isVirtualComboTogetherContainer(container)
      let lineCount = ctx.cratePeekLineCount
        ? ctx.cratePeekLineCount(container, shellPackItem)
        : 0
      const subtitleParts: string[] = []
      if (lineCount > 0) {
        subtitleParts.push(ctx.formatCrateLineCount(lineCount))
      }
      const { shelfLabel, shelfKey } = materialJourneyShelfForContainer(
        container,
        ctx.shellPackItemForContainer,
      )
      return {
        id: `inv-crate-${container.id}`,
        kind: (isVirtualSet ? 'virtual_crate' : 'crate') as MaterialJourneyTaskKind,
        container,
        title: container.label,
        subtitle: subtitleParts.length > 0 ? subtitleParts.join(' · ') : null,
        openQty: 0,
        doneQty: 1,
        maxForwardQty: 0,
        isOpen: false,
        isDone: true,
        badges: (isVirtualSet ? ['virtual_crate'] : ['crate']) as MaterialJourneyTaskBadge[],
        canMove: false,
        canMoveBack: false,
        maxMoveBackQty: 0,
        canOpenSheet: false,
        categoryName: null,
        shelfLabel,
        shelfKey,
        packCrateHint: null,
      }
    })

  return [...crateRows, ...comboRows, ...looseRows]
}

/** @deprecated use buildMaterialJourneyTasks */
export function buildMaterialJourneyLooseTasks(
  packItems: ActivityPackItem[],
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow[] {
  return buildMaterialJourneyTasks(packItems, ctx)
}

export function sortMaterialJourneyTasks(
  rows: MaterialJourneyTaskRow[],
  journeyStep: JourneyStep,
): MaterialJourneyTaskRow[] {
  const cratesFirst =
    journeyStep === 'issue' ||
    journeyStep === 'transport_out' ||
    journeyStep === 'transport_back'
  return [...rows].sort((a, b) => {
    if (a.kind === 'not_taken' && b.kind !== 'not_taken') return 1
    if (b.kind === 'not_taken' && a.kind !== 'not_taken') return -1
    if (cratesFirst) {
      const kindOrder = (kind: MaterialJourneyTaskKind) =>
        isMaterialJourneyCrateKind(kind) ? 0 : kind === 'combo' ? 1 : 2
      const kindDiff = kindOrder(a.kind) - kindOrder(b.kind)
      if (kindDiff !== 0) return kindDiff
    }
    const catA = (a.categoryName ?? '').localeCompare(b.categoryName ?? '', undefined, { sensitivity: 'base' })
    if (catA !== 0) return catA
    return a.title.localeCompare(b.title, undefined, { sensitivity: 'base' })
  })
}

/** @deprecated use sortMaterialJourneyTasks */
export const sortMaterialJourneyLooseTasks = sortMaterialJourneyTasks

export function filterMaterialJourneyTasksByTab(
  rows: MaterialJourneyTaskRow[],
  tab: MaterialJourneyFilterTab,
): MaterialJourneyTaskRow[] {
  if (tab === 'byShelf') return rows.filter((row) => row.isOpen || row.kind === 'not_taken')
  if (tab === 'open') return rows.filter((row) => row.isOpen || row.kind === 'not_taken')
  return rows.filter((row) => row.isDone)
}
