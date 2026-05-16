import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ComboComponent } from '@/api/materials'
import type { RackContentsItem } from '@/api/storageLocations'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  buildLineOverlaysFromCrateCheck,
  containerSectionsFromCheckOverlaysOnly,
  isCrateCheckDisplayLine,
  overlayContainerSections,
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

export const WAREHOUSE_PREVIEW_LINE_PREFIX = 'wh-preview-'

export function isWarehousePreviewContainerLine(ci: { id: string }): boolean {
  return ci.id.startsWith(WAREHOUSE_PREVIEW_LINE_PREFIX)
}

export function isNonActionableContainerLine(ci: { id: string }): boolean {
  return isCrateCheckDisplayLine(ci) || isWarehousePreviewContainerLine(ci)
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

export function warehousePreviewContainerItem(
  containerId: string,
  materialItemId: string,
  materialName: string,
  qty: number,
  lineIdSuffix?: string,
): ActivityPackContainerItem {
  const suffix = lineIdSuffix ?? materialItemId
  return {
    id: `${WAREHOUSE_PREVIEW_LINE_PREFIX}${suffix}`,
    pack_container_id: containerId,
    material_item_id: materialItemId,
    material_batch_id: null,
    quantity_packed: Math.max(0, Math.floor(qty) || 0),
    quantity_issued: 0,
    quantity_returned: 0,
    condition_out: 'ok',
    notes: null,
    material_name: materialName,
  }
}

function buildWarehouseFixedMidSet(
  warehouseTemplateMids: Set<string> | undefined,
  comboComponents: ComboComponent[],
): Set<string> {
  const whSet = new Set<string>(warehouseTemplateMids ?? [])
  for (const cc of comboComponents) {
    const mid = (cc.component_material?.id ?? '').trim()
    if (mid) whSet.add(mid)
  }
  return whSet
}

function mergeTemplateContainerLines(
  containerId: string,
  packLines: ActivityPackContainerItem[],
  whSet: Set<string>,
  warehouseContents: RackContentsItem[] | undefined,
  crateShellMaterialItemId: string | undefined,
  comboComponents: ComboComponent[],
  materialFallback: string,
): ActivityPackContainerItem[] {
  const rows = [...packLines]
  const existingMids = new Set(
    rows.map((ci) => (ci.material_item_id ?? '').trim()).filter(Boolean),
  )
  const shellMid = (crateShellMaterialItemId ?? '').trim()

  if (warehouseContents) {
    for (const row of warehouseContents) {
      const mid = (row.material_id ?? '').trim()
      if (!mid || !whSet.has(mid) || mid === shellMid || existingMids.has(mid)) continue
      const name = (row.material_name && String(row.material_name).trim()) || materialFallback
      rows.push(warehousePreviewContainerItem(containerId, mid, name, row.qty))
      existingMids.add(mid)
    }
  }

  for (const cc of comboComponents) {
    const mid = (cc.component_material?.id ?? '').trim()
    if (!mid || !whSet.has(mid) || mid === shellMid || existingMids.has(mid)) continue
    const name = (cc.component_material?.name ?? '').trim() || materialFallback
    const qty = Math.max(0, Math.floor(Number(cc.qty) || 0))
    rows.push(warehousePreviewContainerItem(containerId, mid, name, qty, `combo-${cc.id}`))
    existingMids.add(mid)
  }

  return rows
}

export function packContainerItemSections(
  containerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseTemplateMids: Set<string> | undefined,
  titles: { fixed: string; extra: string; all: string },
  options?: {
    warehouseContents?: RackContentsItem[]
    crateShellMaterialItemId?: string
    comboComponents?: ComboComponent[]
    materialFallback?: string
  },
): PackContainerItemSection[] {
  const comboComponents = options?.comboComponents ?? []
  const materialFallback = options?.materialFallback ?? 'Material'
  const whSet = buildWarehouseFixedMidSet(warehouseTemplateMids, comboComponents)
  const packLines = containerItemsByContainerId[containerId] ?? []

  const lines =
    whSet.size > 0
      ? mergeTemplateContainerLines(
          containerId,
          packLines,
          whSet,
          options?.warehouseContents,
          options?.crateShellMaterialItemId,
          comboComponents,
          materialFallback,
        )
      : [...packLines]

  if (lines.length === 0) return []

  if (whSet.size === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines }]
  }

  const fixed: ActivityPackContainerItem[] = []
  const extra: ActivityPackContainerItem[] = []
  for (const ci of lines) {
    const mid = (ci.material_item_id ?? '').trim()
    if (mid && whSet.has(mid)) fixed.push(ci)
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
    return [{ subsectionKey: 'all', title: titles.all, lines }]
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
      id: ci.id.includes(':') ? ci.id.slice(ci.id.indexOf(':') + 1) : ci.id.replace(WAREHOUSE_PREVIEW_LINE_PREFIX, ''),
      materialName: (ci.material_name && String(ci.material_name).trim()) || materialFallback,
      quantity: ci.quantity_packed ?? 0,
      materialItemId: ci.material_item_id ?? null,
    })),
  }))
}

function peekLineIdFromContainerItem(ci: ActivityPackContainerItem): string {
  if (ci.id.startsWith(WAREHOUSE_PREVIEW_LINE_PREFIX)) {
    const rest = ci.id.slice(WAREHOUSE_PREVIEW_LINE_PREFIX.length)
    return rest.includes(':') ? rest : rest
  }
  return ci.id
}

/** Peek line ids aligned with shellForwardLineKey(subsection, id). */
function containerSectionsToPeekForCheck(
  sections: PackContainerItemSection[],
  materialFallback: string,
): PackCrateShellPeekSection[] {
  return sections.map((sec) => ({
    subsectionKey: sec.subsectionKey,
    title: sec.title,
    lines: sec.lines.map((ci) => {
      const rawId = peekLineIdFromContainerItem(ci)
      const lineId = rawId.startsWith('combo-') ? rawId.slice('combo-'.length) : rawId
      return {
        id: lineId,
        materialName: (ci.material_name && String(ci.material_name).trim()) || materialFallback,
        quantity: ci.quantity_packed ?? 0,
        materialItemId: ci.material_item_id ?? null,
      }
    }),
  }))
}

export function buildShellContainerTemplateSections(
  container: ActivityPackContainer,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseTemplateMids: Set<string> | undefined,
  warehouseContents: RackContentsItem[] | undefined,
  comboComponents: ComboComponent[],
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
): PackContainerItemSection[] {
  return packContainerItemSections(container.id, containerItemsByContainerId, warehouseTemplateMids, titles, {
    warehouseContents,
    crateShellMaterialItemId: (container.container_material_item_id ?? '').trim() || undefined,
    comboComponents,
    materialFallback,
  })
}

export function packContainerItemSectionsWithReality(
  container: ActivityPackContainer,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseTemplateMids: Set<string> | undefined,
  warehouseContents: RackContentsItem[] | undefined,
  comboComponents: ComboComponent[],
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
  shellPackItemId: string | undefined,
  crateCheckSnapshotsByPackItemId: Record<string, CrateCheckSnapshot>,
  useRealityView: boolean,
): PackContainerItemSection[] {
  const template = buildShellContainerTemplateSections(
    container,
    containerItemsByContainerId,
    warehouseTemplateMids,
    warehouseContents,
    comboComponents,
    titles,
    materialFallback,
  )
  if (!shellPackItemId || !useRealityView) return template
  const snap = crateCheckSnapshotsByPackItemId[shellPackItemId]
  if (!snap) return template
  const overlays = buildLineOverlaysFromCrateCheck(snap)
  if (overlays.length === 0) return template
  if (template.length === 0) {
    return containerSectionsFromCheckOverlaysOnly(container, overlays, titles)
  }
  return overlayContainerSections(template, container, overlays).map((s) => ({
    ...s,
    title:
      s.subsectionKey === 'fixed'
        ? titles.fixed
        : s.subsectionKey === 'extra'
          ? titles.extra
          : titles.all,
  }))
}

/** Stückliste der Phys.-Kombi, wenn noch kein Pack-Behälter / Lager-Kiste verknüpft ist. */
export function peekSectionsFromComboComponents(
  comboComponents: ComboComponent[],
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
): PackCrateShellPeekSection[] {
  if (comboComponents.length === 0) return []

  const fixed: PackCrateShellPeekSection['lines'] = []
  const extra: PackCrateShellPeekSection['lines'] = []
  for (const cc of comboComponents) {
    const line = {
      id: `combo-${cc.id}`,
      materialName: (cc.component_material?.name ?? '').trim() || materialFallback,
      quantity: Math.max(0, Math.floor(Number(cc.qty)) || 0),
      materialItemId: (cc.component_material?.id ?? '').trim() || null,
    }
    if (cc.is_optional) extra.push(line)
    else fixed.push(line)
  }

  const out: PackCrateShellPeekSection[] = []
  if (fixed.length > 0) {
    out.push({ subsectionKey: 'fixed', title: titles.fixed, lines: fixed })
  }
  if (extra.length > 0) {
    out.push({ subsectionKey: 'extra', title: titles.extra, lines: extra })
  }
  if (out.length === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines: [...fixed, ...extra] }]
  }
  return out
}

export function crateShellForwardPeekSections(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseTemplateMids: Set<string> | undefined,
  warehouseContents: RackContentsItem[] | undefined,
  comboComponents: ComboComponent[],
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
): PackCrateShellPeekSection[] {
  const c = packShellContainerForPackItem(pi, packContainers)
  if (!c) {
    return peekSectionsFromComboComponents(comboComponents, titles, materialFallback)
  }
  const sections = buildShellContainerTemplateSections(
    c,
    containerItemsByContainerId,
    warehouseTemplateMids,
    warehouseContents,
    comboComponents,
    titles,
    materialFallback,
  )
  return containerSectionsToPeekForCheck(sections, materialFallback)
}

export function crateShellPeekSectionsForPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>,
  warehouseContentsByContainerId: Record<string, RackContentsItem[]>,
  comboComponentsByMaterialId: Record<string, ComboComponent[]>,
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
  crateCheckSnapshotsByPackItemId: Record<string, CrateCheckSnapshot>,
  useRealityView: boolean,
): PackCrateShellPeekSection[] {
  const c = packShellContainerForPackItem(pi, packContainers)
  const combo = comboComponentsByMaterialId[pi.materialItemId] ?? []
  if (!c) {
    return peekSectionsFromComboComponents(combo, titles, materialFallback)
  }
  const template = containerSectionsToPeekForCheck(
    buildShellContainerTemplateSections(
      c,
      containerItemsByContainerId,
      containerWarehouseTemplateByContainerId[c.id],
      warehouseContentsByContainerId[c.id],
      comboComponentsByMaterialId[pi.materialItemId] ?? [],
      titles,
      materialFallback,
    ),
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
  warehouseContentsByContainerId: Record<string, RackContentsItem[]>,
  comboComponents: ComboComponent[],
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
  shellPackItemId?: string,
  crateCheckSnapshotsByPackItemId?: Record<string, CrateCheckSnapshot>,
  useRealityView?: boolean,
): PackCrateShellPeekSection[] {
  if (shellPackItemId && crateCheckSnapshotsByPackItemId && useRealityView) {
    const snap = crateCheckSnapshotsByPackItemId[shellPackItemId]
    if (snap) {
      const sections = packContainerItemSectionsWithReality(
        c,
        containerItemsByContainerId,
        containerWarehouseTemplateByContainerId[c.id],
        warehouseContentsByContainerId[c.id],
        comboComponents,
        titles,
        materialFallback,
        shellPackItemId,
        crateCheckSnapshotsByPackItemId,
        true,
      )
      return containerSectionsToPeekForCheck(sections, materialFallback)
    }
  }
  const sections = buildShellContainerTemplateSections(
    c,
    containerItemsByContainerId,
    containerWarehouseTemplateByContainerId[c.id],
    warehouseContentsByContainerId[c.id],
    comboComponents,
    titles,
    materialFallback,
  )
  return containerSectionsToPeek(sections, materialFallback)
}

export function isPackContainerMergedIntoStageLeftRow(
  c: ActivityPackContainer,
  packContainers: ActivityPackContainer[],
  stageLeftItems: ActivityPackItem[],
  activePackStage: string,
): boolean {
  if (
    activePackStage !== 'packed_at_event' &&
    activePackStage !== 'packed_transport_to' &&
    activePackStage !== 'transport_to_at_event'
  ) {
    return false
  }
  for (const p of stageLeftItems) {
    if (!isCrateShellPackItem(p, packContainers)) continue
    const shellC = packShellContainerForPackItem(p, packContainers)
    if (shellC?.id === c.id && getStageLeftQtyForFilter(p) > 0) return true
  }
  return false
}

function getStageLeftQtyForFilter(p: ActivityPackItem): number {
  return Math.max(0, (p.quantityPacked ?? 0) - (p.quantityIssued ?? 0))
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
