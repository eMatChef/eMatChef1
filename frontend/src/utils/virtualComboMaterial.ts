import type { ComboConfigSnapshot } from '@/api/activities'
import type { ActivityMaterialLine } from '@/composables/useActivityCreateWizard'

export type VirtualComboLineContext = {
  id?: string | null
  activity_item_id?: string | null
  material_item_id: string
  material_type?: string | null
  parent_activity_item_id?: string | null
  quantity?: number
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

/** Kombo-Eltern (virtuell) nach activity_item_id. */
export function virtualComboParentByItemId(
  items: VirtualComboLineContext[],
): Map<string, VirtualComboLineContext> {
  const out = new Map<string, VirtualComboLineContext>()
  for (const row of items) {
    if (row.parent_activity_item_id) continue
    if (row.material_type !== 'virtual_combo') continue
    const id = lineItemId(row)
    if (id) out.set(id, row)
  }
  return out
}

/** Kind-Zeile einer noch vorhandenen virtuellen Kombo (keine verwaisten Reste). */
export function isActiveVirtualComboChildRow(
  row: VirtualComboLineContext,
  parentsById: Map<string, VirtualComboLineContext>,
): boolean {
  const parentId = row.parent_activity_item_id
  if (!parentId) return false
  const parent = parentsById.get(parentId)
  return !!parent && parent.material_type === 'virtual_combo'
}

/**
 * Gespeicherte Reservierung je Material (DB):
 * eigenständige Zeilen + gültige virtuelle-Kombo-Kinder (keine verwaisten Kind-Zeilen).
 */
export function reservedQuantityByMaterialItemId(
  items: VirtualComboLineContext[],
  quantityFor: (row: VirtualComboLineContext) => number = (row) => 1,
): Record<string, number> {
  const parentsById = virtualComboParentByItemId(items)
  const m: Record<string, number> = {}
  for (const row of items) {
    const qty = Math.max(0, quantityFor(row))
    if (qty <= 0) continue
    if (row.parent_activity_item_id) {
      if (!isActiveVirtualComboChildRow(row, parentsById)) continue
      m[row.material_item_id] = (m[row.material_item_id] ?? 0) + qty
      continue
    }
    if (row.material_type === 'virtual_combo') continue
    m[row.material_item_id] = (m[row.material_item_id] ?? 0) + qty
  }
  return m
}

/**
 * Kombo-Kind-Mengen je Material (Entwurf): API-Kinder + together-Snapshot.
 * Verwaiste Kind-Zeilen (Eltern gelöscht) werden ignoriert.
 */
export function childQuantityByMaterialItemIdFromItems(
  items: VirtualComboLineContext[],
  options: {
    draftPackModeByItemId?: Record<string, 'together' | 'loose'>
    quantityFor?: (row: VirtualComboLineContext) => number
  } = {},
): Record<string, number> {
  const parentsById = virtualComboParentByItemId(items)
  const qty = options.quantityFor ?? (() => 1)
  const m: Record<string, number> = {}

  for (const row of items) {
    if (!row.parent_activity_item_id) continue
    if (!isActiveVirtualComboChildRow(row, parentsById)) continue
    const q = Math.max(0, qty(row))
    if (q <= 0) continue
    m[row.material_item_id] = (m[row.material_item_id] ?? 0) + q
  }

  for (const row of items) {
    if (row.parent_activity_item_id || row.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(row, options.draftPackModeByItemId) === 'loose') continue
    const comboQty = Math.max(1, qty(row))
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      const mid = c.component_material_id
      if (!mid) continue
      const total =
        typeof c.qty_per_combo === 'number'
          ? c.qty_per_combo * comboQty
          : typeof c.total_qty === 'number'
            ? c.total_qty
            : 0
      if (total > 0) {
        m[mid] = Math.max(m[mid] ?? 0, total)
      }
    }
  }

  return m
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

/** Top-Level-Zeile, die mit anderen Zeilen desselben Materials zusammengeführt werden darf. */
export function isMergeableStandaloneTopLevelItem(
  row: Pick<VirtualComboLineContext, 'parent_activity_item_id' | 'material_type'> & {
    is_replenishment?: boolean
  },
): boolean {
  if (row.parent_activity_item_id) return false
  if (row.material_type === 'virtual_combo' || row.material_type === 'physical_combo') return false
  if (row.is_replenishment) return false
  return true
}

export function hasDuplicateMergeableStandaloneItems(items: VirtualComboLineContext[]): boolean {
  const seen = new Set<string>()
  for (const row of items) {
    if (!isMergeableStandaloneTopLevelItem(row)) continue
    if (seen.has(row.material_item_id)) return true
    seen.add(row.material_item_id)
  }
  return false
}

/** Mehrere Top-Level-Zeilen derselben virtuellen Kombo (z. B. Sarasani doppelt gebucht). */
export function hasDuplicateVirtualComboParents(items: VirtualComboLineContext[]): boolean {
  const seen = new Set<string>()
  for (const row of items) {
    if (row.parent_activity_item_id || row.material_type !== 'virtual_combo') continue
    if (seen.has(row.material_item_id)) return true
    seen.add(row.material_item_id)
  }
  return false
}

export type ActivitySyncItemPayload = {
  material_item_id: string
  quantity: number
  priority?: string
  selected_option_ids?: string[]
  pack_mode?: 'together' | 'loose'
  self_provided_acknowledged?: boolean
}

/** Mehrere eigenständige Top-Level-Zeilen desselben Materials → eine Sync-Zeile (Summe). */
export function buildConsolidatedActivitySyncItems<T extends VirtualComboLineContext & { priority?: string | null }>(
  items: T[],
  options: {
    quantityFor: (row: T) => number
    includeRow: (row: T) => boolean
    extrasForRow?: (row: T) => Omit<ActivitySyncItemPayload, 'material_item_id' | 'quantity' | 'priority'>
  },
): ActivitySyncItemPayload[] {
  const comboAndSpecial: ActivitySyncItemPayload[] = []
  const mergedVirtualCombos = new Map<string, ActivitySyncItemPayload>()
  const mergedByMaterial = new Map<string, ActivitySyncItemPayload>()

  for (const row of items) {
    if (!options.includeRow(row)) continue
    const qty = Math.max(0, options.quantityFor(row))
    if (qty <= 0) continue

    const base = {
      material_item_id: row.material_item_id,
      quantity: qty,
      priority: row.priority ?? undefined,
      ...options.extrasForRow?.(row),
    }

    if (row.material_type === 'virtual_combo') {
      const existing = mergedVirtualCombos.get(row.material_item_id)
      if (!existing) {
        mergedVirtualCombos.set(row.material_item_id, { ...base })
      } else if (base.pack_mode === 'loose') {
        // Doppelbuchung derselben Kombo: eine Planungszeile behalten, lose bevorzugen.
        existing.pack_mode = 'loose'
      }
      continue
    }

    if (!isMergeableStandaloneTopLevelItem(row)) {
      comboAndSpecial.push(base)
      continue
    }

    const existing = mergedByMaterial.get(row.material_item_id)
    if (!existing) {
      mergedByMaterial.set(row.material_item_id, { ...base })
    } else {
      existing.quantity += qty
    }
  }

  return [...mergedVirtualCombos.values(), ...comboAndSpecial, ...mergedByMaterial.values()]
}

/**
 * Sichtbare Zeilen: mehrere virtuelle-Kombo-Eltern (gleiches Material) → eine Anzeigezeile.
 * Bei Doppelbuchungen: Menge der ersten Zeile (nicht summieren), pack_mode «loose» bevorzugen.
 */
export function mergeVirtualComboParentRowsForMaterialTable<
  T extends VirtualComboLineContext & { id?: string | null },
>(
  comboParentRows: T[],
): Array<{ representative: T; members: T[] }> {
  const out: Array<{ representative: T; members: T[] }> = []
  const mergeIdx = new Map<string, number>()

  for (const row of comboParentRows) {
    if (row.parent_activity_item_id || row.material_type !== 'virtual_combo') continue
    const idx = mergeIdx.get(row.material_item_id)
    if (idx === undefined) {
      mergeIdx.set(row.material_item_id, out.length)
      out.push({ representative: row, members: [row] })
    } else {
      out[idx].members.push(row)
    }
  }

  return out
}

/** Effektiver pack_mode über alle Duplikat-Eltern derselben virtuellen Kombo. */
export function mergedVirtualComboPackMode<
  T extends VirtualComboLineContext & { id?: string | null },
>(
  members: T[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
): 'together' | 'loose' {
  for (const row of members) {
    if (resolveVirtualComboPackMode(row, draftPackModeByItemId) === 'loose') return 'loose'
  }
  return 'together'
}

/**
 * Sync-Payload: eigenständige Mengen um losen Kombo-Set-Bedarf kürzen (nur Extra bleibt).
 * Kind-Zeilen kommen aus expandVirtualComboLine — keine doppelten Top-Level-Komponenten.
 */
export function applyLooseComboExtraOnlyToSyncItems(
  items: ActivitySyncItemPayload[],
  looseNeedByMaterialId: Record<string, number>,
  virtualComboMaterialIds: Set<string>,
): ActivitySyncItemPayload[] {
  return items
    .map((item) => {
      if (virtualComboMaterialIds.has(item.material_item_id)) return item
      const need = looseNeedByMaterialId[item.material_item_id] ?? 0
      if (need <= 0) return item
      return { ...item, quantity: Math.max(0, item.quantity - need) }
    })
    .filter((item) => item.quantity > 0)
}

/**
 * Sichtbare Zeilen für die Materialliste: eigenständige Duplikate pro material_item_id zusammenführen.
 */
export function mergeStandaloneRowsForMaterialTable<T extends VirtualComboLineContext & { id?: string | null }>(
  visibleRows: T[],
): Array<{ representative: T; members: T[] }> {
  const out: Array<{ representative: T; members: T[] }> = []
  const mergeIdx = new Map<string, number>()

  for (const row of visibleRows) {
    if (!isMergeableStandaloneTopLevelItem(row)) {
      out.push({ representative: row, members: [row] })
      continue
    }
    const idx = mergeIdx.get(row.material_item_id)
    if (idx === undefined) {
      mergeIdx.set(row.material_item_id, out.length)
      out.push({ representative: row, members: [row] })
    } else {
      out[idx].members.push(row)
    }
  }

  return out
}

/** «Zusammen als Set»: Set-Bedarf je Komponente (Snapshot), pro virtuelle Kombo einmal. */
export function togetherComboTotalNeedByMaterialId(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): Record<string, number> {
  const out: Record<string, number> = {}
  const seenComboMaterial = new Set<string>()
  for (const row of items) {
    if (row.parent_activity_item_id || row.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(row, draftPackModeByItemId) !== 'together') continue
    if (seenComboMaterial.has(row.material_item_id)) continue
    seenComboMaterial.add(row.material_item_id)
    const comboQty = Math.max(1, quantityFor(row))
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      const mid = c.component_material_id
      if (!mid) continue
      const total =
        typeof c.total_qty === 'number'
          ? c.total_qty
          : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
      if (total > 0) out[mid] = (out[mid] ?? 0) + total
    }
  }
  return out
}

/** Gesamter Stock-Bedarf virtueller Kombos (lose + zusammen) je Komponente. */
export function virtualComboStockNeedByMaterialId(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): Record<string, number> {
  const loose = looseComboTotalNeedByMaterialId(items, draftPackModeByItemId, quantityFor)
  const together = togetherComboTotalNeedByMaterialId(items, draftPackModeByItemId, quantityFor)
  const out = { ...loose }
  for (const [mid, qty] of Object.entries(together)) {
    out[mid] = (out[mid] ?? 0) + qty
  }
  return out
}

/** Kind-Mengen lose vs. zusammen (DB-Zeilen) je Komponente. */
export function virtualComboChildQtyByMaterialAndMode(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): { loose: Record<string, number>; together: Record<string, number> } {
  const parentsById = virtualComboParentByItemId(items)
  const loose: Record<string, number> = {}
  const together: Record<string, number> = {}
  for (const row of items) {
    if (!row.parent_activity_item_id) continue
    if (!isActiveVirtualComboChildRow(row, parentsById)) continue
    const parent = parentsById.get(row.parent_activity_item_id)
    if (!parent) continue
    const q = Math.max(0, quantityFor(row))
    if (q <= 0) continue
    const mid = row.material_item_id
    if (resolveVirtualComboPackMode(parent, draftPackModeByItemId) === 'loose') {
      loose[mid] = (loose[mid] ?? 0) + q
    } else {
      together[mid] = (together[mid] ?? 0) + q
    }
  }
  return { loose, together }
}

/**
 * Extra-Menge einer eigenständigen Zeile:
 * Warenkorb minus Set-Bedarf, der noch nicht als Kind-Zeile gebucht ist.
 */
export function extraStandaloneQtyForMaterial(
  materialId: string,
  standaloneRawQty: number,
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): number {
  const looseNeed = looseComboTotalNeedByMaterialId(items, draftPackModeByItemId, quantityFor)[materialId] ?? 0
  const togetherNeed = togetherComboTotalNeedByMaterialId(items, draftPackModeByItemId, quantityFor)[materialId] ?? 0
  const totalComboNeed = looseNeed + togetherNeed
  if (totalComboNeed <= 0) return Math.max(0, standaloneRawQty)

  const childByMode = virtualComboChildQtyByMaterialAndMode(items, draftPackModeByItemId, quantityFor)
  const childCovered =
    (childByMode.loose[materialId] ?? 0) + (childByMode.together[materialId] ?? 0)

  // Nach «Vorhandene nutzen» steht oft schon die Extra-Menge in der DB (z. B. 11 < Set-Bedarf 39).
  if (standaloneRawQty > 0 && standaloneRawQty < totalComboNeed) {
    return standaloneRawQty
  }

  if (childCovered >= totalComboNeed) {
    return Math.max(0, standaloneRawQty - totalComboNeed)
  }

  const comboNeedNotInChildren = totalComboNeed - childCovered
  return Math.max(0, standaloneRawQty - comboNeedNotInChildren)
}

/** Noch von eigenständigen Zeilen abzuziehender Kombo-Bedarf (nicht schon als Kind-Zeile). */
export function virtualComboStandaloneReduceByMaterialId(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): Record<string, number> {
  const childByMode = virtualComboChildQtyByMaterialAndMode(items, draftPackModeByItemId, quantityFor)
  const looseNeed = looseComboTotalNeedByMaterialId(items, draftPackModeByItemId, quantityFor)
  const togetherNeed = togetherComboTotalNeedByMaterialId(items, draftPackModeByItemId, quantityFor)
  const out: Record<string, number> = {}
  const mids = new Set([...Object.keys(looseNeed), ...Object.keys(togetherNeed)])
  for (const mid of mids) {
    const reduce =
      Math.max(0, (looseNeed[mid] ?? 0) - (childByMode.loose[mid] ?? 0)) +
      Math.max(0, (togetherNeed[mid] ?? 0) - (childByMode.together[mid] ?? 0))
    if (reduce > 0) out[mid] = reduce
  }
  return out
}

/**
 * Lose Kombo: gesamter Set-Bedarf je Komponente aus allen losen Eltern (Snapshot),
 * unabhängig davon ob Kind-Zeilen schon in der DB existieren.
 */
export function looseComboTotalNeedByMaterialId(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): Record<string, number> {
  const out: Record<string, number> = {}
  const seenComboMaterial = new Set<string>()
  for (const row of items) {
    if (row.parent_activity_item_id || row.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(row, draftPackModeByItemId) !== 'loose') continue
    if (seenComboMaterial.has(row.material_item_id)) continue
    seenComboMaterial.add(row.material_item_id)
    const comboQty = Math.max(1, quantityFor(row))
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      const mid = c.component_material_id
      if (!mid) continue
      const total =
        typeof c.total_qty === 'number'
          ? c.total_qty
          : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
      if (total > 0) out[mid] = (out[mid] ?? 0) + total
    }
  }
  return out
}

/** Lose Kombo: Set-Bedarf je Komponente (Snapshot), wenn noch keine Kind-Zeilen in der DB. */
export function looseComboComponentNeedByMaterialId(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): Record<string, number> {
  const out: Record<string, number> = {}
  for (const row of items) {
    if (row.parent_activity_item_id || row.material_type !== 'virtual_combo') continue
    if (resolveVirtualComboPackMode(row, draftPackModeByItemId) !== 'loose') continue
    const parentId = lineItemId(row)
    if (!parentId) continue
    const hasChildren = items.some((c) => c.parent_activity_item_id === parentId)
    if (hasChildren) continue
    const comboQty = Math.max(1, quantityFor(row))
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      const mid = c.component_material_id
      if (!mid) continue
      const total =
        typeof c.total_qty === 'number'
          ? c.total_qty
          : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
      if (total > 0) out[mid] = (out[mid] ?? 0) + total
    }
  }
  return out
}

/** Kind-Mengen aus API-Zeilen (lose Kombo). */
export function looseChildQuantityByMaterialId(
  items: VirtualComboLineContext[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
  quantityFor: (row: VirtualComboLineContext) => number,
): Record<string, number> {
  const parentsById = virtualComboParentByItemId(items)
  const out: Record<string, number> = {}
  for (const row of items) {
    if (!row.parent_activity_item_id) continue
    if (!isActiveVirtualComboChildRow(row, parentsById)) continue
    const parent = parentsById.get(row.parent_activity_item_id)
    if (!parent || resolveVirtualComboPackMode(parent, draftPackModeByItemId) !== 'loose') continue
    const q = Math.max(0, quantityFor(row))
    if (q > 0) out[row.material_item_id] = (out[row.material_item_id] ?? 0) + q
  }
  return out
}

export function virtualComboComponentReduceParts(
  comboRow: VirtualComboLineContext & { config_snapshot?: ComboConfigSnapshot | null },
  comboQty: number,
): Array<{ materialItemId: string; reduceBy: number }> {
  if (comboRow.material_type !== 'virtual_combo') return []
  const qty = Math.max(1, comboQty)
  const resolved = comboRow.config_snapshot?.resolved_components ?? []
  const out: Array<{ materialItemId: string; reduceBy: number }> = []
  for (const c of resolved) {
    const mid = c.component_material_id
    if (!mid) continue
    const total =
      typeof c.total_qty === 'number'
        ? c.total_qty
        : Math.max(0, (c.qty_per_combo ?? 0) * qty)
    if (total > 0) out.push({ materialItemId: mid, reduceBy: total })
  }
  return out
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
