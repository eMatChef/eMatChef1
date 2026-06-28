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
  shouldIncludePackItemOnStageLeft,
  shouldShowContainerOnRightMirror,
  shouldShowContainerOnStageLeft,
  shouldShowPackContainerInWarehouseVisibleList,
  shouldShowPackContainerOnConfirmedPackedRight,
  type PackWorkflowContainerContext,
  type PackWorkflowListContext,
} from '@/components/activities/packWorkflowRules'
import {
  getStageRightQty,
  isPackConfirmedStage,
  isPackForwardToEventStage,
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
  if (!ctx.listCtx.showPackContainersUi || !isPackForwardToEventStage(ctx.packStage)) {
    return false
  }
  const shell = ctx.shellPackItemForContainer(container.id)
  if (!shell || !isCrateShellPackItem(shell, ctx.packContainers)) return false
  if (!ctx.stageLeftItems.some((pi) => pi.id === shell.id)) return false
  return shouldShowContainerOnStageLeft(container.id, ctx.containerCtx)
}

function shouldHideShellMaterialForJourneyCrateRow(
  pi: ActivityPackItem,
  ctx: MaterialJourneyTaskBuildContext,
): boolean {
  if (!ctx.listCtx.showPackContainersUi) return false
  if (!isCrateShellPackItem(pi, ctx.packContainers)) return false
  const container = packShellContainerForPackItem(pi, ctx.packContainers)
  if (!container) return false

  // Packen: Shell nur als Packkisten-Zeile, nie zusätzlich als lose Position.
  if (isPackConfirmedStage(ctx.packStage)) return true

  if (!isPackForwardToEventStage(ctx.packStage)) return false
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
  return rows.some((row) => row.isOpen && row.kind !== 'crate')
}

export function countOpenLooseComboMaterialTasks(rows: MaterialJourneyTaskRow[]): number {
  return rows.filter((row) => row.isOpen && row.kind !== 'crate').length
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

  return {
    id: `crate-${container.id}`,
    kind: 'crate',
    container,
    title: container.label,
    subtitle: buildSubtitleParts(subtitleParts),
    openQty,
    doneQty,
    maxForwardQty: forwardUnits > 0 ? forwardUnits : issueable,
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
    .filter((pi) => !shouldHideShellMaterialForJourneyCrateRow(pi, ctx))
    .map((pi) => buildMaterialJourneyLooseTask(pi, ctx))
    .filter((row) => row.isOpen || row.isDone)

  const comboRows = journeyItems
    .filter((pi) => isPhysicalComboPackItem(pi))
    .filter((pi) => !shouldHideShellMaterialForJourneyCrateRow(pi, ctx))
    .map((pi) => buildMaterialJourneyComboTask(pi, ctx))
    .filter((row) => row.isOpen || row.isDone)

  const hasOpenLooseComboPackWork =
    looseRows.some((row) => row.isOpen) || comboRows.some((row) => row.isOpen)
  const crateCtx: MaterialJourneyTaskBuildContext = {
    ...ctx,
    hasOpenLooseComboPackWork,
  }

  const crateRows =
    ctx.listCtx.showPackContainersUi
      ? ctx.packContainers
          .map((c) => buildMaterialJourneyCrateTask(c, crateCtx))
          .filter((row) => row.isOpen || row.isDone)
      : []

  return [...crateRows, ...comboRows, ...looseRows]
}

/** Am Anlass (Logistics): statische Bestandsliste — nur quantity_issued > 0. */
export function buildMaterialJourneyAtEventInventory(
  packItems: ActivityPackItem[],
  ctx: Pick<
    MaterialJourneyTaskBuildContext,
    'packContainers' | 'shellPackItemForContainer' | 'formatCrateLineCount' | 'cratePeekLineCount'
  >,
): MaterialJourneyTaskRow[] {
  const journeyItems = packItemsForMaterialJourney(packItems)

  const looseRows = journeyItems
    .filter((pi) => !isVirtualComboPackItem(pi) && !isPhysicalComboPackItem(pi))
    .filter((pi) => (pi.quantityIssued ?? 0) > 0)
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
      return false
    })
    .map((container) => {
      const shellPackItem = ctx.shellPackItemForContainer(container.id)
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
        kind: 'crate' as const,
        container,
        title: container.label,
        subtitle: subtitleParts.length > 0 ? subtitleParts.join(' · ') : null,
        openQty: 0,
        doneQty: 1,
        maxForwardQty: 0,
        isOpen: false,
        isDone: true,
        badges: ['crate'] as MaterialJourneyTaskBadge[],
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
