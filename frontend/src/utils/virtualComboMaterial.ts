import type { ComboConfigSnapshot } from '@/api/activities'
import type { ActivityMaterialLine } from '@/composables/useActivityCreateWizard'

export type VirtualComboLineContext = {
  id?: string | null
  activity_item_id?: string | null
  material_item_id: string
  material_type?: string | null
  parent_activity_item_id?: string | null
  config_snapshot?: ComboConfigSnapshot | null
  pack_mode?: 'together' | 'loose'
}

function lineItemId(row: VirtualComboLineContext): string | null {
  return row.activity_item_id ?? row.id ?? null
}

/** Effektiver pack_mode einer virtuellen Kombo (Draft-Override oder Snapshot). */
export function resolveVirtualComboPackMode(
  row: VirtualComboLineContext,
  draftPackModeByItemId?: Record<string, 'together' | 'loose'>,
): 'together' | 'loose' {
  const id = lineItemId(row)
  if (id) {
    const draft = draftPackModeByItemId?.[id]
    if (draft === 'together' || draft === 'loose') return draft
  }
  const mode = row.config_snapshot?.pack_mode ?? row.pack_mode
  return mode === 'together' ? 'together' : 'loose'
}

/** Stock-Teile virtueller Kombos mit pack_mode «together» — keine eigenständigen Zeilen dafür. */
export function togetherVirtualComboComponentMaterialIds(
  items: VirtualComboLineContext[],
  draftPackModeByItemId?: Record<string, 'together' | 'loose'>,
): Set<string> {
  const out = new Set<string>()
  for (const row of items) {
    if (row.parent_activity_item_id) continue
    if (row.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(row, draftPackModeByItemId) !== 'together') continue
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      if (c.component_material_id) out.add(c.component_material_id)
    }
  }
  return out
}

/** Summe lose Kind-Zeilen virtueller Kombos je Material. */
export function virtualComboLooseChildQtyForMaterial(
  items: VirtualComboLineContext[],
  materialId: string,
  draftPackModeByItemId?: Record<string, 'together' | 'loose'>,
  quantityFor?: (row: VirtualComboLineContext) => number,
): number {
  const parentsById = new Map<string, VirtualComboLineContext>()
  for (const row of items) {
    const id = lineItemId(row)
    if (id) parentsById.set(id, row)
  }
  const qty = quantityFor ?? ((row: VirtualComboLineContext) => 1)
  let sum = 0
  for (const row of items) {
    if (!row.parent_activity_item_id) continue
    if (row.material_item_id !== materialId) continue
    const parent = parentsById.get(row.parent_activity_item_id)
    if (!parent || parent.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(parent, draftPackModeByItemId) !== 'loose') continue
    sum += qty(row)
  }
  return sum
}

/** Stock-Teile virtueller Kombos mit pack_mode «loose» (aus Snapshot) — keine doppelten Standalone-Zeilen. */
export function looseVirtualComboComponentMaterialIds(
  items: VirtualComboLineContext[],
  draftPackModeByItemId?: Record<string, 'together' | 'loose'>,
): Set<string> {
  const out = new Set<string>()
  for (const row of items) {
    if (row.parent_activity_item_id) continue
    if (row.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(row, draftPackModeByItemId) !== 'loose') continue
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      if (c.component_material_id) out.add(c.component_material_id)
    }
  }
  return out
}

/** Sichtbarkeit in der editierbaren Materialliste (Detail-Entwurf). */
export function isActivityItemVisibleInMaterialTable(
  row: VirtualComboLineContext,
  items: VirtualComboLineContext[],
  draftPackModeByItemId?: Record<string, 'together' | 'loose'>,
): boolean {
  const parentsById = new Map<string, VirtualComboLineContext>()
  for (const item of items) {
    const id = lineItemId(item)
    if (id) parentsById.set(id, item)
  }

  if (row.parent_activity_item_id) {
    const parent = parentsById.get(row.parent_activity_item_id)
    if (!parent || parent.material_type !== 'virtual_combo') return false
    return resolveVirtualComboPackMode(parent, draftPackModeByItemId) === 'loose'
  }

  if (row.material_type === 'virtual_combo') return true

  const togetherComponents = togetherVirtualComboComponentMaterialIds(items, draftPackModeByItemId)
  if (togetherComponents.has(row.material_item_id) && !row.parent_activity_item_id) {
    // «Together»: Kind-Zeilen ausgeblendet; eigenständige Top-Level-Zeile = Extra-Menge.
    return row.material_type !== 'virtual_combo'
  }

  const looseComponents = looseVirtualComboComponentMaterialIds(items, draftPackModeByItemId)
  if (looseComponents.has(row.material_item_id) && !row.parent_activity_item_id) {
    // Lose Kombo-Teile primär als Kinder; eigenständige Top-Level-Zeile = Extra-Menge.
    return true
  }
  return true
}

/** Top-Level-Zeilen fürs PUT …/items (Kombo-Eltern + eigenständige Zeilen inkl. Extra-Mengen). */
export function shouldIncludeTopLevelInVirtualComboSync(
  row: VirtualComboLineContext,
  items: VirtualComboLineContext[],
  _draftPackModeByItemId?: Record<string, 'together' | 'loose'>,
): boolean {
  if (row.parent_activity_item_id) return false
  return true
}

/** Kind-Zeile einer virtuellen Kombo (lose) — Menge nicht einzeln editierbar. */
export function isVirtualComboChildLine(row: Pick<ActivityMaterialLine, 'parent_activity_item_id'>): boolean {
  return !!row.parent_activity_item_id
}

/** Kombo-Bedarf je Material (stock-Teile) aus virtuellen Kombo-Eltern-Zeilen. */
export function comboFloorQtyByMaterialId(lines: ActivityMaterialLine[]): Record<string, number> {
  const out: Record<string, number> = {}
  for (const row of lines) {
    if (row.material_type !== 'virtual_combo') continue
    const packMode = row.config_snapshot?.pack_mode ?? row.pack_mode
    if (packMode === 'loose') continue
    const snap = row.config_snapshot
    const comboQty = Math.max(1, row.quantity)
    const resolved = snap?.resolved_components ?? []
    if (resolved.length > 0) {
      for (const c of resolved) {
        const mid = c.component_material_id
        const total =
          typeof c.total_qty === 'number'
            ? c.total_qty
            : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
        if (mid && total > 0) {
          out[mid] = (out[mid] ?? 0) + total
        }
      }
      continue
    }
    // Fallback ohne Snapshot: keine Floor-Angabe möglich
  }
  return out
}

/** Summe eigenständiger Einzelzeilen (ohne Kombo-Hülle und ohne Kombo-Kinder). */
export function standaloneQtyByMaterialId(lines: ActivityMaterialLine[]): Record<string, number> {
  const out: Record<string, number> = {}
  for (const row of lines) {
    if (row.parent_activity_item_id) continue
    if (row.material_type === 'physical_combo' || row.material_type === 'virtual_combo') continue
    out[row.material_item_id] = (out[row.material_item_id] ?? 0) + row.quantity
  }
  return out
}

export type VirtualComboFloorOptions = {
  /** Kind-Zeilen aus API (Detail); Wizard: leer → Kombo-Bedarf gilt als durch Eltern gedeckt. */
  childQuantityByMaterialItemId?: Record<string, number>
  /** Wizard: true → effektive Kind-Menge = Kombo-Floor (Backend expandiert beim Speichern). */
  treatComboFloorAsChildCoverage?: boolean
  /** Basis-Minimum (Wizard 1, Detail-Entwurf 0). */
  baseMinQty?: number
}

/**
 * Mindestmenge einer eigenständigen Einzelzeile: nicht unter den offenen Kombo-Bedarf
 * fallen, den Kind-Zeilen noch nicht abdecken.
 */
export function minStandaloneQtyForLine(
  row: ActivityMaterialLine,
  lines: ActivityMaterialLine[],
  options: VirtualComboFloorOptions = {},
): number {
  if (row.material_type === 'physical_combo' || row.material_type === 'virtual_combo') {
    return options.baseMinQty ?? 0
  }

  const mid = row.material_item_id
  const comboFloor = comboFloorQtyByMaterialId(lines)[mid] ?? 0
  if (comboFloor <= 0) return options.baseMinQty ?? 0

  let childQty = options.childQuantityByMaterialItemId?.[mid] ?? 0
  if (options.treatComboFloorAsChildCoverage) {
    childQty = Math.max(childQty, comboFloor)
  }

  const standalone = standaloneQtyByMaterialId(lines)
  const otherStandalone = (standalone[mid] ?? 0) - row.quantity
  const openComboNeed = Math.max(0, comboFloor - childQty - otherStandalone)

  return Math.max(options.baseMinQty ?? 0, openComboNeed)
}

export function canRemoveStandaloneLine(
  row: ActivityMaterialLine,
  lines: ActivityMaterialLine[],
  options: VirtualComboFloorOptions = {},
): boolean {
  if (row.parent_activity_item_id) return false
  return minStandaloneQtyForLine(row, lines, options) <= 0
}

export function canSetStandaloneQty(
  row: ActivityMaterialLine,
  lines: ActivityMaterialLine[],
  nextQty: number,
  options: VirtualComboFloorOptions = {},
): boolean {
  const minQ = minStandaloneQtyForLine(row, lines, options)
  return nextQty >= minQ
}

export type MaterialSummaryEntry = {
  key: string
  name: string
  quantity: number
  /** Aufgelöstes Kombo-Teil */
  fromCombo?: string
  selfProvided?: boolean
}

/** Virtuelle Kombo-Eltern in effektive Teile für Übersicht / Sidebar auflösen. */
export function expandMaterialLinesForSummary(lines: ActivityMaterialLine[]): MaterialSummaryEntry[] {
  const out: MaterialSummaryEntry[] = []
  for (const row of lines) {
    if (row.material_type === 'virtual_combo') {
      const snap = row.config_snapshot
      const comboQty = Math.max(1, row.quantity)
      const resolved = snap?.resolved_components ?? []
      if (resolved.length > 0) {
        for (const c of resolved) {
          const total =
            typeof c.total_qty === 'number'
              ? c.total_qty
              : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
          out.push({
            key: `vc-${row.material_item_id}-r-${c.component_material_id}`,
            name: c.name,
            quantity: total,
            fromCombo: row.material_name,
          })
        }
        for (const c of snap?.self_provided ?? []) {
          out.push({
            key: `vc-${row.material_item_id}-s-${c.component_material_id}`,
            name: c.name,
            quantity: c.total_qty,
            fromCombo: row.material_name,
            selfProvided: true,
          })
        }
        continue
      }
    }
    out.push({
      key: `line-${row.material_item_id}`,
      name: row.material_name,
      quantity: row.quantity,
    })
  }
  return out
}

export function formatMaterialSummaryEntries(
  entries: MaterialSummaryEntry[],
  opts?: { maxItems?: number; countLabel?: (n: number) => string },
): string {
  const max = opts?.maxItems ?? 3
  if (entries.length === 0) return ''
  if (entries.length > max && opts?.countLabel) {
    return opts.countLabel(entries.length)
  }
  return entries.map((e) => `${e.name} ×${e.quantity}`).join(', ')
}

/** Mini-Snapshot aus Konfigurator-Daten für Wizard-Zeile (Floor + Übersicht). */
export function buildVirtualComboConfigSnapshot(input: {
  quantity: number
  selectedOptionIds: string[]
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  resolvedSelfProvided?: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  packMode?: 'together' | 'loose'
  selfProvidedAcknowledged?: boolean
}): ComboConfigSnapshot {
  const comboQty = Math.max(1, input.quantity)
  return {
    combo_qty: comboQty,
    selected_option_ids: [...input.selectedOptionIds],
    resolved_components: input.resolvedStock.map((c) => ({
      component_material_id: c.materialItemId,
      name: c.name,
      qty_per_combo: c.qtyPerCombo,
      total_qty: c.qtyPerCombo * comboQty,
      component_source: 'stock' as const,
    })),
    self_provided: (input.resolvedSelfProvided ?? []).map((c) => ({
      component_material_id: c.materialItemId,
      name: c.name,
      total_qty: c.qtyPerCombo * comboQty,
    })),
    ...(input.packMode ? { pack_mode: input.packMode } : {}),
    ...(input.selfProvidedAcknowledged ? { self_provided_acknowledged: true } : {}),
  }
}
