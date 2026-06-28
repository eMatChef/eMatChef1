import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ComboComponent } from '@/api/materials'
import type { RackContentsItem } from '@/api/storageLocations'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import { formatBatchSerialHint } from '@/components/activities/packCrateForwardCheck'
import {
  buildLineOverlaysFromCrateCheck,
  containerSectionsFromCheckOverlaysOnly,
  isCrateCheckDisplayLine,
  overlayContainerSections,
  overlayPeekSections,
  type CrateCheckSnapshot,
} from '@/components/activities/packCrateCheckReality'

import type { PackStage } from '@/components/activities/packStageQuantities'
import { isPackForwardWarehouseUiStage } from '@/components/activities/packStageQuantities'

export type ComboPreviewLineStageQty = { done: number; total: number; rem: number }

/** Phys.-Kombi-Vorschauzeile: Stückzahlen aus Shell-Pipeline (Set × Komponente pro Set). */
export function comboPreviewLineShellDerivedStageQty(
  ci: ActivityPackContainerItem,
  shell: ActivityPackItem | undefined,
  stage: PackStage,
): ComboPreviewLineStageQty | null {
  if (!isWarehousePreviewContainerLine(ci)) return null
  if (!shell || shell.materialType !== 'physical_combo') return null
  if (!isPackForwardWarehouseUiStage(stage)) return null

  const perSet = Math.max(0, Math.floor(Number(ci.quantity_packed) || 0))
  if (perSet < 1) return { done: 0, total: 0, rem: 0 }

  let shellTotal = 0
  let shellDone = 0
  if (stage === 'at_event_transport_back') {
    shellTotal = shell.quantityIssued ?? 0
    shellDone = shell.quantityTransportBack ?? 0
  } else if (stage === 'transport_to_at_event') {
    shellTotal = shell.quantityTransportTo ?? 0
    shellDone = shell.quantityIssued ?? 0
  } else if (stage === 'packed_transport_to') {
    shellTotal = shell.quantityPacked ?? 0
    shellDone = shell.quantityTransportTo ?? 0
  } else if (stage === 'packed_at_event') {
    shellTotal = shell.quantityPacked ?? 0
    shellDone = shell.quantityIssued ?? 0
  } else {
    return null
  }

  const total = shellTotal * perSet
  const done = shellDone * perSet
  return { done, total, rem: Math.max(0, total - done) }
}

export function isPhysicalComboPreviewContainerLine(
  ci: ActivityPackContainerItem,
  shell: ActivityPackItem | undefined,
): boolean {
  return isWarehousePreviewContainerLine(ci) && shell?.materialType === 'physical_combo'
}

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

/** Vorschau-/Check-Zeile auf echte Pack-Kistenposition mappen (Einlagern, Meldungen). */
export function resolveActionableContainerLine(
  containerId: string,
  ci: ActivityPackContainerItem,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): ActivityPackContainerItem {
  if (!isNonActionableContainerLine(ci)) return ci
  const mid = (ci.material_item_id ?? '').trim()
  if (!mid) return ci
  const real = (containerItemsByContainerId[containerId] ?? []).find(
    (row) => row.material_item_id === mid && !isNonActionableContainerLine(row),
  )
  return real ?? ci
}

export function shellPackItemForContainer(
  container: ActivityPackContainer,
  packItems: ActivityPackItem[],
): ActivityPackItem | undefined {
  const batchId = (container.container_batch_id ?? '').trim()
  if (batchId) {
    const byBatch = packItems.find((p) => (p.linkedContainerBatchId ?? '').trim() === batchId)
    if (byBatch) return byBatch
  }
  const mid = (container.container_material_item_id ?? '').trim()
  if (mid) {
    return packItems.find((p) => p.materialItemId === mid)
  }
  return undefined
}

export function shellPackItemForContainerId(
  containerId: string,
  packContainers: ActivityPackContainer[],
  packItems: ActivityPackItem[],
): ActivityPackItem | undefined {
  const container = packContainers.find((c) => c.id === containerId)
  if (!container) return undefined
  return shellPackItemForContainer(container, packItems)
}

export function packShellContainerForPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  virtualContainerIdByPackItemId?: Record<string, string>,
): ActivityPackContainer | undefined {
  const virtualId = (virtualContainerIdByPackItemId?.[pi.id] ?? '').trim()
  if (virtualId) {
    const virtual = packContainers.find((c) => c.id === virtualId)
    if (virtual) return virtual
  }
  const linkBatch = (pi.linkedContainerBatchId ?? '').trim()
  if (linkBatch) {
    const byBatch = packContainers.find((c) => (c.container_batch_id ?? '').trim() === linkBatch)
    if (byBatch) return byBatch
  }
  const mid = pi.materialItemId
  const byMaterial = packContainers.filter((c) => (c.container_material_item_id ?? '').trim() === mid)
  if (byMaterial.length === 1) return byMaterial[0]
  return byMaterial[0]
}

/** Lager-Charge für Kisten-Vorschau: container_batch_id oder linkedContainerBatchId der Shell-Position. */
export function resolvePackContainerWarehouseBatchId(
  container: ActivityPackContainer,
  packItems: ActivityPackItem[],
  packContainers?: ActivityPackContainer[],
): string {
  const direct = (container.container_batch_id ?? '').trim()
  if (direct) return direct

  const containerMatId = (container.container_material_item_id ?? '').trim()
  if (containerMatId) {
    const shell = packItems.find((p) => p.materialItemId === containerMatId)
    const fromShell = (shell?.linkedContainerBatchId ?? '').trim()
    if (fromShell) return fromShell
  }

  const containers = packContainers ?? [container]
  for (const pi of packItems) {
    if (packShellContainerForPackItem(pi, containers)?.id !== container.id) continue
    const fromLink = (pi.linkedContainerBatchId ?? '').trim()
    if (fromLink) return fromLink
  }

  return ''
}

export {
  crateShellExcludedFromLooseForwardList,
  isOrphanShellWithoutPackContainer,
  hideShellPackItemOnConfirmedPackedLeft,
  packContainerVisibleOnConfirmedPackedRight,
  isPackContainerMergedIntoStageLeftRow,
} from '@/components/activities/packWorkflowRules'

export function isCrateShellPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  virtualContainerIdByPackItemId?: Record<string, string>,
): boolean {
  if (packShellContainerForPackItem(pi, packContainers, virtualContainerIdByPackItemId) != null) return true
  if (pi.materialType !== 'physical_combo') return false
  if ((pi.linkedContainerLabel ?? '').trim() !== '') return true
  if ((pi.linkedContainerBatchId ?? '').trim() !== '') return true
  return false
}

/** Phys.-Kombi als Set: Pipeline auf Pack-Position, kein Zwang Pack-Behälter (ohne Referenz-Charge). */
export function isPhysicalComboAsSet(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  virtualContainerIdByPackItemId?: Record<string, string>,
): boolean {
  if (pi.materialType !== 'physical_combo') return false
  if ((pi.linkedContainerBatchId ?? '').trim() !== '') return false
  return packShellContainerForPackItem(pi, packContainers, virtualContainerIdByPackItemId) == null
}

/**
 * Phys.-Kombi mit Referenz-Lager-Charge, Pack-Behälter noch nicht angelegt (z. B. Kochkiste auf Camp).
 */
export function linkedShellCombosNeedingPackContainer(
  packItems: ActivityPackItem[],
  packContainers: ActivityPackContainer[],
  virtualContainerIdByPackItemId?: Record<string, string>,
): ActivityPackItem[] {
  return packItems.filter(
    (pi) =>
      pi.materialType === 'physical_combo' &&
      (pi.linkedContainerBatchId ?? '').trim() !== '' &&
      !isPhysicalComboAsSet(pi, packContainers, virtualContainerIdByPackItemId) &&
      packShellContainerForPackItem(pi, packContainers, virtualContainerIdByPackItemId) == null,
  )
}

/** Material-ID der Phys.-Kombi / Shell — nicht als Zeile im eigenen Pack-Behälter anzeigen. */
export function crateShellMaterialItemIdForContainer(
  container: ActivityPackContainer,
  shellPackItem?: ActivityPackItem | null,
): string | undefined {
  const fromContainer = (container.container_material_item_id ?? '').trim()
  if (fromContainer) return fromContainer
  const fromPackItem = (shellPackItem?.materialItemId ?? '').trim()
  return fromPackItem || undefined
}

/** Referenz-Behälter der Kombi (Stückliste/Lager) — nicht als Inhalt der eigenen Kiste listen. */
export function linkedContainerComponentMaterialIds(
  shellPackItem: ActivityPackItem | null | undefined,
  comboComponents: ComboComponent[],
): Set<string> {
  const out = new Set<string>()
  const linkBatch = (shellPackItem?.linkedContainerBatchId ?? '').trim()
  if (!linkBatch) return out
  for (const cc of comboComponents) {
    if ((cc.component_batch?.id ?? '').trim() !== linkBatch) continue
    const mid = (cc.component_material?.id ?? '').trim()
    if (mid) out.add(mid)
  }
  return out
}

function isExcludedShellContainerLine(
  materialItemId: string,
  shellMid: string,
  linkedContainerMids: Set<string>,
): boolean {
  if (!materialItemId) return false
  if (shellMid && materialItemId === shellMid) return true
  return linkedContainerMids.has(materialItemId)
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
  shellMid?: string,
): Set<string> {
  const whSet = new Set<string>(warehouseTemplateMids ?? [])
  for (const cc of comboComponents) {
    const mid = (cc.component_material?.id ?? '').trim()
    if (mid) whSet.add(mid)
  }
  if (shellMid) whSet.delete(shellMid)
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
  linkedContainerMids: Set<string>,
): ActivityPackContainerItem[] {
  const rows = [...packLines]
  const existingMids = new Set(
    rows.map((ci) => (ci.material_item_id ?? '').trim()).filter(Boolean),
  )
  const shellMid = (crateShellMaterialItemId ?? '').trim()

  if (warehouseContents) {
    for (const row of warehouseContents) {
      const mid = (row.material_id ?? '').trim()
      if (
        !mid ||
        !whSet.has(mid) ||
        isExcludedShellContainerLine(mid, shellMid, linkedContainerMids) ||
        existingMids.has(mid)
      ) {
        continue
      }
      const name = (row.material_name && String(row.material_name).trim()) || materialFallback
      rows.push(warehousePreviewContainerItem(containerId, mid, name, row.qty))
      existingMids.add(mid)
    }
  }

  for (const cc of comboComponents) {
    const mid = (cc.component_material?.id ?? '').trim()
    if (
      !mid ||
      !whSet.has(mid) ||
      isExcludedShellContainerLine(mid, shellMid, linkedContainerMids) ||
      existingMids.has(mid)
    ) {
      continue
    }
    const name = (cc.component_material?.name ?? '').trim() || materialFallback
    const qty = Math.max(0, Math.floor(Number(cc.qty) || 0))
    rows.push(warehousePreviewContainerItem(containerId, mid, name, qty, `combo-${cc.id}`))
    existingMids.add(mid)
  }

  return rows.filter((ci) => {
    const mid = (ci.material_item_id ?? '').trim()
    return !isExcludedShellContainerLine(mid, shellMid, linkedContainerMids)
  })
}

export function packContainerItemSections(
  containerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseTemplateMids: Set<string> | undefined,
  titles: { fixed: string; extra: string; all: string },
  options?: {
    warehouseContents?: RackContentsItem[]
    crateShellMaterialItemId?: string
    linkedContainerComponentMids?: Set<string>
    comboComponents?: ComboComponent[]
    materialFallback?: string
  },
): PackContainerItemSection[] {
  const comboComponents = options?.comboComponents ?? []
  const materialFallback = options?.materialFallback ?? 'Material'
  const shellMid = (options?.crateShellMaterialItemId ?? '').trim()
  const linkedContainerMids = options?.linkedContainerComponentMids ?? new Set<string>()
  const whSet = buildWarehouseFixedMidSet(warehouseTemplateMids, comboComponents, shellMid || undefined)
  for (const mid of linkedContainerMids) whSet.delete(mid)
  const packLines = (containerItemsByContainerId[containerId] ?? []).filter((ci) => {
    const mid = (ci.material_item_id ?? '').trim()
    return !isExcludedShellContainerLine(mid, shellMid, linkedContainerMids)
  })

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
          linkedContainerMids,
        )
      : [...packLines]

  if (lines.length === 0) return []

  if (whSet.size === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines }]
  }

  const useShellFixExtraSplit = comboComponents.length > 0 || shellMid !== ''

  const fixed: ActivityPackContainerItem[] = []
  const extra: ActivityPackContainerItem[] = []

  if (useShellFixExtraSplit) {
    const packIds = new Set(packLines.map((p) => p.id))
    for (const ci of lines) {
      const mid = (ci.material_item_id ?? '').trim()
      if (isExcludedShellContainerLine(mid, shellMid, linkedContainerMids)) {
        continue
      }
      if (isWarehousePreviewContainerLine(ci) || !packIds.has(ci.id)) {
        fixed.push(ci)
      } else {
        extra.push(ci)
      }
    }
    return [
      { subsectionKey: 'fixed', title: titles.fixed, lines: fixed },
      { subsectionKey: 'extra', title: titles.extra, lines: extra },
    ]
  }

  for (const ci of lines) {
    const mid = (ci.material_item_id ?? '').trim()
    if (isExcludedShellContainerLine(mid, shellMid, linkedContainerMids)) continue
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
      serialHint: formatBatchSerialHint(ci.serial_number, ci.batch_label),
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
        serialHint: formatBatchSerialHint(ci.serial_number, ci.batch_label),
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
  shellPackItem?: ActivityPackItem | null,
): PackContainerItemSection[] {
  const combo = comboComponents
  return packContainerItemSections(container.id, containerItemsByContainerId, warehouseTemplateMids, titles, {
    warehouseContents,
    crateShellMaterialItemId: crateShellMaterialItemIdForContainer(container, shellPackItem),
    linkedContainerComponentMids: linkedContainerComponentMaterialIds(shellPackItem, combo),
    comboComponents: combo,
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
  shellPackItem?: ActivityPackItem | null,
): PackContainerItemSection[] {
  const template = buildShellContainerTemplateSections(
    container,
    containerItemsByContainerId,
    warehouseTemplateMids,
    warehouseContents,
    comboComponents,
    titles,
    materialFallback,
    shellPackItem,
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
      serialHint: formatBatchSerialHint(
        cc.component_batch?.serial_number,
        cc.component_batch?.label,
      ),
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
    pi,
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
      pi,
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
  shellPackItem?: ActivityPackItem | null,
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
        shellPackItem,
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
    shellPackItem,
  )
  return containerSectionsToPeek(sections, materialFallback)
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
