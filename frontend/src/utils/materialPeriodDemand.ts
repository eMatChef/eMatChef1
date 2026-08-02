import type { ActivityMaterialLine } from '@/composables/useActivityCreateWizard'
import {
  looseVirtualComboComponentMaterialIds,
  togetherVirtualComboComponentMaterialIds,
  type VirtualComboLineContext,
} from '@/utils/virtualComboMaterial'

export type MaterialLineDemandSlice = Pick<
  ActivityMaterialLine,
  | 'material_item_id'
  | 'quantity'
  | 'parent_activity_item_id'
  | 'material_type'
  | 'config_snapshot'
  | 'pack_mode'
  | 'activity_item_id'
>

function draftPackModeByItemIdFromLines(
  lines: MaterialLineDemandSlice[],
): Record<string, 'together' | 'loose'> {
  const m: Record<string, 'together' | 'loose'> = {}
  for (const row of lines) {
    if (row.material_type !== 'virtual_combo') continue
    const id = row.activity_item_id
    if (!id) continue
    const mode = row.config_snapshot?.pack_mode ?? row.pack_mode
    m[id] = mode === 'together' ? 'together' : 'loose'
  }
  return m
}

/** Eigenständige Top-Level-Menge (ohne Set-Kinder / Kombo-Hülle). */
export function standaloneDemandInLines(
  materialItemId: string,
  lines: MaterialLineDemandSlice[],
): number {
  let standalone = 0
  for (const row of lines) {
    if (row.material_item_id !== materialItemId) continue
    if (row.material_type === 'virtual_combo') continue
    if (row.parent_activity_item_id) continue
    standalone += row.quantity
  }
  return standalone
}

/** Kind-Mengen aus sichtbaren Zeilen (lose Kombo-Kinder). */
function childDemandInLines(materialItemId: string, lines: MaterialLineDemandSlice[]): number {
  let sum = 0
  for (const row of lines) {
    if (row.material_item_id !== materialItemId) continue
    if (!row.parent_activity_item_id) continue
    sum += row.quantity
  }
  return sum
}

/** Together-Snapshot, wenn Kinder nicht als eigene Zeilen sichtbar sind. */
function togetherSnapshotDemandInLines(
  materialItemId: string,
  lines: MaterialLineDemandSlice[],
  draftPackModeByItemId: Record<string, 'together' | 'loose'>,
): number {
  let max = 0
  for (const row of lines) {
    if (row.material_type !== 'virtual_combo') continue
    const id = row.activity_item_id
    const mode =
      (id ? draftPackModeByItemId[id] : undefined) ??
      row.config_snapshot?.pack_mode ??
      row.pack_mode
    if (mode === 'loose') continue
    const comboQty = Math.max(1, row.quantity)
    for (const c of row.config_snapshot?.resolved_components ?? []) {
      if (c.component_material_id !== materialItemId) continue
      const total =
        typeof c.total_qty === 'number'
          ? c.total_qty
          : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
      max = Math.max(max, total)
    }
  }
  return max
}

/**
 * Gesamtbedarf eines Materials in der Materialliste:
 * eigenständige Top-Level-Zeilen + Kombo-Kind-Mengen (Zeilen oder Snapshot/Wizard-Map).
 */
export function totalMaterialDemandInLines(
  materialItemId: string,
  lines: MaterialLineDemandSlice[],
  childQuantityByMaterialItemId: Record<string, number> = {},
): number {
  const standalone = standaloneDemandInLines(materialItemId, lines)
  const childInLines = childDemandInLines(materialItemId, lines)
  const draftPack = draftPackModeByItemIdFromLines(lines)

  if (childInLines > 0) {
    return standalone + childInLines
  }

  const fromMap = childQuantityByMaterialItemId[materialItemId] ?? 0
  const fromTogether = togetherSnapshotDemandInLines(materialItemId, lines, draftPack)
  const childPart = Math.max(fromMap, fromTogether)

  return standalone + childPart
}

/**
 * Rest-Spalte pro Zeile: eigene Menge, nicht den Gesamtbedarf aller Zeilen desselben Materials.
 */
export function restDisplayDemandForRow(
  row: MaterialLineDemandSlice,
  lines: MaterialLineDemandSlice[],
  childQuantityByMaterialItemId: Record<string, number> = {},
): number {
  if (row.material_type === 'virtual_combo') return row.quantity
  if (row.parent_activity_item_id) return row.quantity

  const draftPack = draftPackModeByItemIdFromLines(lines)
  const together = togetherVirtualComboComponentMaterialIds(
    lines as VirtualComboLineContext[],
    draftPack,
  )
  const loose = looseVirtualComboComponentMaterialIds(
    lines as VirtualComboLineContext[],
    draftPack,
  )
  if (together.has(row.material_item_id) || loose.has(row.material_item_id)) {
    return row.quantity
  }

  return totalMaterialDemandInLines(row.material_item_id, lines, childQuantityByMaterialItemId)
}

/** Verfügbares Kontingent für diese Zeile (Kapazität abzüglich anderer Zeilen desselben Materials). */
export function restCapacityForRow(
  row: MaterialLineDemandSlice,
  lines: MaterialLineDemandSlice[],
  periodCapacity: number | undefined,
  childQuantityByMaterialItemId: Record<string, number> = {},
): number | undefined {
  if (periodCapacity === undefined) return undefined
  const total = totalMaterialDemandInLines(
    row.material_item_id,
    lines,
    childQuantityByMaterialItemId,
  )
  const other = Math.max(0, total - row.quantity)
  return Math.max(0, periodCapacity - other)
}

/**
 * Rest-Spalte: Bei «zusammen als Set» nur die lose Extra-Zeile anzeigen —
 * Set-Teile stehen inline unter der Kombo, nicht doppelt in Rest.
 */
export function restDisplayDemandForMaterial(
  materialItemId: string,
  lines: MaterialLineDemandSlice[],
  childQuantityByMaterialItemId: Record<string, number> = {},
): number {
  const together = togetherVirtualComboComponentMaterialIds(
    lines as VirtualComboLineContext[],
    draftPackModeByItemIdFromLines(lines),
  )
  if (together.has(materialItemId)) {
    return standaloneDemandInLines(materialItemId, lines)
  }
  return totalMaterialDemandInLines(materialItemId, lines, childQuantityByMaterialItemId)
}

/** Maximal zulässiger Gesamtbedarf im Zeitraum. */
export function periodCapacityForMaterial(
  materialItemId: string,
  rawAvailable: number | undefined,
  savedSum: number,
  options?: { excludeCurrentActivity?: boolean },
): number | undefined {
  if (rawAvailable === undefined) return undefined
  // Verfügbarkeits-API mit excludeActivityId: raw = gesamtes Zeitfenster-Pol für diese Aktivität.
  if (options?.excludeCurrentActivity) {
    return Math.max(0, rawAvailable)
  }
  return Math.max(0, rawAvailable + savedSum)
}

export function materialDemandShortage(
  demand: number,
  capacity: number | undefined,
): number {
  if (capacity === undefined) return 0
  return Math.max(0, demand - capacity)
}

export type SeparateBookStockPart = {
  materialItemId: string
  name: string
  qtyPerCombo: number
}

export type SeparateBookShortage = {
  materialItemId: string
  name: string
  standaloneQty: number
  existingComboQty: number
  newComboNeed: number
  totalAfter: number
  available: number
  shortage: number
  /** Reduktion auf eigenständige Zeilen, damit totalAfter ≤ available. */
  suggestedStandaloneReduce: number
  /** Nach Anpassung noch offener Engpass (Set-Menge müsste sinken). */
  remainingShortage: number
}

export function computeSeparateBookShortages(
  parts: SeparateBookStockPart[],
  comboQty: number,
  options: {
    standaloneQtyFor: (materialItemId: string) => number
    existingComboQtyFor: (materialItemId: string) => number
    /** Frei laut API (ohne bereits gespeicherte Reservierung dieser Aktivität). */
    rawAvailableFor: (materialItemId: string) => number | undefined
    savedQtyFor: (materialItemId: string) => number
    /** Verfügbarkeits-API mit excludeActivityId — Kapazität = raw, nicht raw + saved. */
    excludeCurrentActivity?: boolean
  },
): SeparateBookShortage[] {
  const out: SeparateBookShortage[] = []
  const qty = Math.max(1, comboQty)

  for (const part of parts) {
    const newComboNeed = Math.max(0, part.qtyPerCombo) * qty
    if (newComboNeed <= 0) continue

    const standaloneQty = Math.max(0, options.standaloneQtyFor(part.materialItemId))
    const existingComboQty = Math.max(0, options.existingComboQtyFor(part.materialItemId))
    const totalAfter = standaloneQty + existingComboQty + newComboNeed
    const rawAvailable = options.rawAvailableFor(part.materialItemId)
    const savedQty = Math.max(0, options.savedQtyFor(part.materialItemId))
    const capacity = periodCapacityForMaterial(part.materialItemId, rawAvailable, savedQty, {
      excludeCurrentActivity: options.excludeCurrentActivity === true,
    })
    if (capacity === undefined) continue

    const shortage = materialDemandShortage(totalAfter, capacity)
    if (shortage <= 0) continue

    const suggestedStandaloneReduce = Math.min(standaloneQty, shortage)
    const remainingShortage = Math.max(0, shortage - suggestedStandaloneReduce)

    out.push({
      materialItemId: part.materialItemId,
      name: part.name,
      standaloneQty,
      existingComboQty,
      newComboNeed,
      totalAfter,
      available: capacity,
      shortage,
      suggestedStandaloneReduce,
      remainingShortage,
    })
  }

  return out
}

export function combinePartsFromSeparateShortages(
  shortages: SeparateBookShortage[],
): Array<{ materialItemId: string; reduceBy: number }> {
  return shortages
    .filter((s) => s.suggestedStandaloneReduce > 0)
    .map((s) => ({
      materialItemId: s.materialItemId,
      reduceBy: s.suggestedStandaloneReduce,
    }))
}
