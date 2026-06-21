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
  shouldShowPackContainerInWarehouseVisibleList,
  type PackWorkflowContainerContext,
  type PackWorkflowListContext,
} from '@/components/activities/packWorkflowRules'
import type { PackStage } from '@/components/activities/packStageQuantities'
import { getStageRightQty } from '@/components/activities/packStageQuantities'

export type MaterialJourneyTaskKind = 'loose' | 'crate' | 'combo'

export type MaterialJourneyTaskBadge = 'physical_combo' | 'consumable' | 'js' | 'crate' | 'intent_group'

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
  canOpenSheet: boolean
  categoryName: string | null
  shelfLabel: string
  shelfKey: string
  intentId: string | null
  intentMemberCount: number
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
  canOpenSheet: boolean
  formatCrateLineCount: (count: number) => string
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  cratePeekLineCount?: (
    container: ActivityPackContainer,
    shellPackItem?: ActivityPackItem,
  ) => number
  intentMemberCount?: (intentId: string) => number
}

function taskBadges(pi: ActivityPackItem, ctx: MaterialJourneyTaskBuildContext): MaterialJourneyTaskBadge[] {
  const badges: MaterialJourneyTaskBadge[] = []
  if (pi.intentId) badges.push('intent_group')
  if (pi.isConsumable) badges.push('consumable')
  if (pi.isJsMaterial) badges.push('js')
  return badges
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

  const subtitleParts: string[] = []
  const rack = packRackLabel(pi)
  if (rack) subtitleParts.push(rack)
  if (pi.storageSlotName?.trim()) subtitleParts.push(pi.storageSlotName.trim())
  if (openQty > 0) subtitleParts.push(String(openQty))

  const shelfLabel = materialJourneyShelfLabel(pi)
  const shelfKey = materialJourneyShelfKey(shelfLabel)
  const intentId = pi.intentId
  const intentMemberCount = intentId ? (ctx.intentMemberCount?.(intentId) ?? 0) : 0

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
    canOpenSheet: false,
    categoryName: pi.categoryName?.trim() || null,
    shelfLabel,
    shelfKey,
    intentId,
    intentMemberCount,
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
  const isOpen = shouldShowPackContainerInWarehouseVisibleList(
    container,
    ctx.containerCtx,
    ctx.stageLeftItems,
  )
  const isDone =
    !isOpen &&
    shouldShowContainerOnRightMirror(container.id, ctx.containerCtx)
  const openQty = isOpen ? Math.max(1, issueable) : 0
  const doneQty = isDone ? 1 : 0
  const shellPackItem = ctx.shellPackItemForContainer(container.id)
  const lineCount = ctx.cratePeekLineCount
    ? ctx.cratePeekLineCount(container, shellPackItem)
    : (ctx.containerCtx.containerItemsForContainer?.(container.id) ?? []).length
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
    canOpenSheet: ctx.canOpenSheet && isOpen && issueable > 0,
    categoryName: null,
    shelfLabel,
    shelfKey,
    intentId: null,
    intentMemberCount: 0,
  }
}

export function buildMaterialJourneyTasks(
  packItems: ActivityPackItem[],
  ctx: MaterialJourneyTaskBuildContext,
): MaterialJourneyTaskRow[] {
  const journeyItems = packItemsForMaterialJourney(packItems)

  const looseRows = journeyItems
    .filter((pi) => !isVirtualComboPackItem(pi) && !isPhysicalComboPackItem(pi))
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
