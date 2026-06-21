import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  isPhysicalComboPackItem,
  isVirtualComboPackItem,
  packMaterialDisplayName,
  packRackLabel,
} from '@/components/activities/packMaterialDisplay'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  materialJourneyShelfForContainer,
  materialJourneyShelfKey,
  materialJourneyShelfLabel,
} from '@/components/activities/materialJourneyRegalGroups'
import { packItemsForMaterialJourney } from '@/components/activities/materialJourneyJsSummary'
import {
  shouldIncludePackItemOnStageLeft,
  shouldShowContainerOnRightMirror,
  shouldShowContainerOnStageLeft,
  shouldShowPackContainerInWarehouseVisibleList,
  shouldShowPackContainerOnConfirmedPackedRight,
  type PackWorkflowContainerContext,
  type PackWorkflowListContext,
} from '@/components/activities/packWorkflowRules'
import { getStageRightQty, isPackForwardToEventStage } from '@/components/activities/packStageQuantities'
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
  if (!ctx.listCtx.showPackContainersUi || !isPackForwardToEventStage(ctx.packStage)) {
    return false
  }
  const shell = ctx.shellPackItemForContainer(container.id)
  if (!shell || !isCrateShellPackItem(shell, ctx.packContainers)) return false
  if (!ctx.stageLeftItems.some((pi) => pi.id === shell.id)) return false
  return shouldShowContainerOnStageLeft(container.id, ctx.containerCtx)
}

function shouldHideLooseShellForJourneyCrateRow(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): boolean {
  if (!ctx.listCtx.showPackContainersUi || !isPackForwardToEventStage(ctx.packStage)) {
    return false
  }
  if (!isCrateShellPackItem(pi, ctx.packContainers)) return false
  const container = packShellContainerForPackItem(pi, ctx.packContainers)
  if (!container) return false
  return shouldShowPackContainerInJourneyList(container, ctx)
}

export type MaterialJourneyTaskKind = 'loose' | 'crate' | 'combo'

export type MaterialJourneyTaskBadge = 'physical_combo' | 'consumable' | 'js' | 'crate' | 'pack_crate'

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
  const openQty = ctx.listCtx.effectiveStageLeftQty(pi)
  const doneQty = ctx.listCtx.getStageRightQty(pi)
  const isOpen = shouldIncludePackItemOnStageLeft(pi, ctx.listCtx)
  const isDone =
    !isOpen &&
    getStageRightQty(pi, ctx.packStage, ctx.listCtx.profile) > 0
  const maxForwardQty = ctx.maxForwardQty(pi)
  const canMove = ctx.canMoveItem(pi) && maxForwardQty > 0 && isOpen
  const maxMoveBackQty = ctx.rightQtyForMoveBack?.(pi) ?? 0
  const canMoveBack =
    Boolean(ctx.canMoveBackItem?.(pi)) && isDone && maxMoveBackQty > 0

  const subtitleParts: string[] = []
  if (ctx.showShelfLocation !== false) {
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
  const onConfirmedPackedRight =
    ctx.packStage === 'confirmed_packed' &&
    shouldShowPackContainerOnConfirmedPackedRight(
      container,
      ctx.containerCtx,
      ctx.stageLeftItems,
    )
  const isOpen = onConfirmedPackedRight
    ? false
    : shouldShowPackContainerInJourneyList(container, ctx)
  const isDone =
    onConfirmedPackedRight ||
    (!isOpen && shouldShowContainerOnRightMirror(container.id, ctx.containerCtx))
  const openQty = isOpen ? Math.max(1, issueable) : 0
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

  return {
    id: `crate-${container.id}`,
    kind: 'crate',
    container,
    title: container.label,
    subtitle: buildSubtitleParts(subtitleParts),
    openQty,
    doneQty,
    maxForwardQty: issueable,
    isOpen,
    isDone,
    badges: ['crate'],
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

  const looseRows = journeyItems
    .filter((pi) => !isVirtualComboPackItem(pi) && !isPhysicalComboPackItem(pi))
    .filter((pi) => !shouldHideLooseShellForJourneyCrateRow(pi, ctx))
    .map((pi) => buildMaterialJourneyLooseTask(pi, ctx))
    .filter((row) => row.isOpen || row.isDone)

  const comboRows = journeyItems
    .filter((pi) => isPhysicalComboPackItem(pi))
    .map((pi) => buildMaterialJourneyComboTask(pi, ctx))
    .filter((row) => row.isOpen || row.isDone)

  const crateRows =
    ctx.listCtx.showPackContainersUi
      ? ctx.packContainers
          .map((c) => buildMaterialJourneyCrateTask(c, ctx))
          .filter((row) => row.isOpen || row.isDone)
      : []

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
    if (cratesFirst) {
      const kindOrder = (kind: MaterialJourneyTaskKind) =>
        kind === 'crate' ? 0 : kind === 'combo' ? 1 : 2
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
  if (tab === 'byShelf') return rows.filter((row) => row.isOpen)
  return rows.filter((row) => (tab === 'open' ? row.isOpen : row.isDone))
}
