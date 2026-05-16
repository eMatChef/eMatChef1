import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  buildLineOverlaysFromCrateCheck,
  overlayPeekSections,
  type CrateCheckSnapshot,
} from '@/components/activities/packCrateCheckReality'

export type ShellCrateBackDeviation = {
  id: string
  materialName: string
  detail: string
}

export type PackContainerItemSection = {
  subsectionKey: string
  title: string
  lines: ActivityPackContainerItem[]
}

export function packShellContainerForPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
): ActivityPackContainer | undefined {
  const mid = pi.materialItemId
  const linkBatch = (pi.linkedContainerBatchId ?? '').trim()
  for (const c of packContainers) {
    if (c.container_material_item_id === mid) return c
    if (linkBatch && c.container_batch_id === linkBatch) return c
  }
  return undefined
}

export function isCrateShellPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
): boolean {
  if (pi.materialType !== 'physical_combo') return false
  if ((pi.linkedContainerLabel ?? '').trim() !== '') return true
  if ((pi.linkedContainerBatchId ?? '').trim() !== '') return true
  return packShellContainerForPackItem(pi, packContainers) != null
}

export function packContainerItemSections(
  containerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseFixedMids: Set<string> | undefined,
  titles: { fixed: string; extra: string; all: string },
): PackContainerItemSection[] {
  const rows = containerItemsByContainerId[containerId] ?? []
  if (rows.length === 0) return []
  if (!warehouseFixedMids || warehouseFixedMids.size === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines: rows }]
  }
  const fixed: ActivityPackContainerItem[] = []
  const extra: ActivityPackContainerItem[] = []
  for (const ci of rows) {
    const mid = (ci.material_item_id ?? '').trim()
    if (mid && warehouseFixedMids.has(mid)) fixed.push(ci)
    else extra.push(ci)
  }
  const out: PackContainerItemSection[] = []
  if (fixed.length > 0) {
    out.push({ subsectionKey: 'fixed', title: titles.fixed, lines: fixed })
  }
  if (extra.length > 0) {
    out.push({ subsectionKey: 'extra', title: titles.extra, lines: extra })
  }
  if (out.length === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines: rows }]
  }
  return out
}

function containerSectionsToPeek(
  sections: PackContainerItemSection[],
  materialFallback: string,
): PackCrateShellPeekSection[] {
  return sections.map((sec) => ({
    subsectionKey: sec.subsectionKey,
    title: sec.title,
    lines: sec.lines.map((ci) => ({
      id: ci.id,
      materialName: (ci.material_name && String(ci.material_name).trim()) || materialFallback,
      quantity: ci.quantity_packed ?? 0,
      materialItemId: ci.material_item_id ?? null,
    })),
  }))
}

export function crateShellForwardPeekSections(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseFixedMids: Set<string> | undefined,
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
): PackCrateShellPeekSection[] {
  const c = packShellContainerForPackItem(pi, packContainers)
  if (!c) return []
  return containerSectionsToPeek(
    packContainerItemSections(c.id, containerItemsByContainerId, warehouseFixedMids, titles),
    materialFallback,
  )
}

export function crateShellPeekSectionsForPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>,
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
  crateCheckSnapshotsByPackItemId: Record<string, CrateCheckSnapshot>,
  useRealityView: boolean,
): PackCrateShellPeekSection[] {
  const c = packShellContainerForPackItem(pi, packContainers)
  if (!c) return []
  const warehouseMids = containerWarehouseTemplateByContainerId[c.id]
  const template = containerSectionsToPeek(
    packContainerItemSections(c.id, containerItemsByContainerId, warehouseMids, titles),
    materialFallback,
  )
  const snap = crateCheckSnapshotsByPackItemId[pi.id]
  if (useRealityView && snap) {
    return overlayPeekSections(template, buildLineOverlaysFromCrateCheck(snap))
  }
  return template
}

export function peekSectionsForShellContainer(
  c: ActivityPackContainer,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>,
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
): PackCrateShellPeekSection[] {
  const warehouseMids = containerWarehouseTemplateByContainerId[c.id]
  return containerSectionsToPeek(
    packContainerItemSections(c.id, containerItemsByContainerId, warehouseMids, titles),
    materialFallback,
  )
}

export function isPackContainerMergedIntoStageLeftRow(
  c: ActivityPackContainer,
  packContainers: ActivityPackContainer[],
  stageLeftItems: ActivityPackItem[],
  activePackStage: string,
): boolean {
  if (activePackStage !== 'packed_issued') return false
  for (const p of stageLeftItems) {
    if (!isCrateShellPackItem(p, packContainers)) continue
    const shellC = packShellContainerForPackItem(p, packContainers)
    if (shellC?.id === c.id && getStageLeftQtyForFilter(p, activePackStage) > 0) return true
  }
  return false
}

/** Minimal left-qty for merge filter (avoids importing stage helpers). */
function getStageLeftQtyForFilter(p: ActivityPackItem, stage: string): number {
  if (stage === 'packed_issued') {
    return Math.max(0, (p.quantityPacked ?? 0) - (p.quantityIssued ?? 0))
  }
  return 0
}

function deviationDetailLabel(
  status: string,
  t: (key: string, params?: Record<string, unknown>) => string,
  missingQty: number,
  replenishQty: number,
): string {
  switch (status) {
    case 'replenish':
    case 'replenish_after_loss':
      return t('activities.packList.shellBackDeviationReplenish', { n: replenishQty || missingQty || 1 })
    case 'not_taken':
      return t('activities.packList.shellBackDeviationNotTaken', { n: missingQty || 1 })
    case 'loss':
      return t('activities.packList.shellBackDeviationLoss', { n: missingQty || 1 })
    case 'repair':
      return t('activities.packList.shellBackDeviationRepair', { n: missingQty || 1 })
    case 'extra':
      return t('activities.packList.shellBackDeviationExtra', { n: missingQty || 1 })
    default:
      return t('activities.packList.shellBackDeviationProblem')
  }
}

export function buildShellCrateBackDeviations(
  snapshot: CrateCheckSnapshot | undefined,
  t: (key: string, params?: Record<string, unknown>) => string,
): ShellCrateBackDeviation[] {
  if (!snapshot) return []
  const overlays = buildLineOverlaysFromCrateCheck(snapshot)
  return overlays
    .filter((o) => o.status !== 'ok')
    .map((o) => ({
      id: o.lineKey,
      materialName: o.materialName,
      detail: deviationDetailLabel(o.status, t, o.sollQty - o.countedQty, o.replenishQty),
    }))
}
