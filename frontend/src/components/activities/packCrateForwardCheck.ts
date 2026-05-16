import type { MaterialStorageLocationRow, MaterialStorageViaComboBlock } from '@/api/materials'
import type { PackCrateCheckLinePayload, PackCrateCheckLineStatus } from '@/api/activityPackCrateCheck'

export type ShellForwardResolution =
  | null
  | 'replenish'
  | 'not_taken'
  | 'loss'
  | 'repair'
  | 'extra'

export type ShellForwardLineReviewStatus = 'ok' | 'problem' | null

export type ShellForwardInventoryPhase = 'none' | 'active' | 'done' | 'skipped'

export type InventoryLocationReview = {
  countedQty: number
  status: 'ok' | 'problem' | null
}

export type ShellForwardLineReview = {
  status: ShellForwardLineReviewStatus
  resolution: ShellForwardResolution
  note: string
  missingQty: number | null
  /** Gezählte Menge in der Kiste (Ist). */
  countedQty: number
  /** Nach Verlust: Ersatz aus Lager in Kiste. */
  doReplenishAfterLoss: boolean
  replenishQty: number | null
  inventoryPhase: ShellForwardInventoryPhase
  inventoryLocationReviews: Record<string, InventoryLocationReview>
  inventoryLocations: MiniInventoryLocationRow[]
}

export type MiniInventoryLocationKind = 'loose' | 'container' | 'combo'

export type MiniInventoryLocationRow = MaterialStorageLocationRow & {
  location_kind: MiniInventoryLocationKind
}

export type ShellForwardCheckLine = {
  key: string
  subsectionKey: string
  materialName: string
  quantity: number
  materialItemId: string | null
  isExtra: boolean
}

export function shellForwardLineKey(subsectionKey: string, lineId: string): string {
  return `${subsectionKey}:${lineId}`
}

export function storageLocationRowKey(loc: MaterialStorageLocationRow): string {
  return [
    loc.rack_id ?? '',
    loc.slot_id ?? '',
    loc.batch_id ?? '',
    loc.container_batch_id ?? '',
    loc.container_caption ?? '',
  ].join('\x1e')
}

/** Ortsbezeichnung ohne Stückzahl (Soll steht an den Mini-Zählern). */
export function formatStorageLocationPlaceLabel(loc: MiniInventoryLocationRow): string {
  if (loc.location_kind === 'combo') {
    const name = (loc.container_caption || '').trim() || 'Kombi'
    return `In Kombi-Kiste «${name}»`
  }
  const parts: string[] = []
  const crate = (loc.container_caption || '').trim()
  if (crate) parts.push(`Kiste «${crate}»`)
  if (loc.storage_address_name) parts.push(loc.storage_address_name)
  if (loc.location_label) parts.push(loc.location_label)
  return parts.join(' · ') || '—'
}

export function defaultInventoryLocationReview(expectedQty: number): InventoryLocationReview {
  return { countedQty: expectedQty, status: null }
}

export function applyInventoryLocationCounted(
  review: InventoryLocationReview,
  expectedQty: number,
): InventoryLocationReview {
  const counted = Math.max(0, Math.floor(Number(review.countedQty)) || 0)
  if (review.status === 'ok' && counted === expectedQty) {
    return { countedQty: counted, status: 'ok' }
  }
  if (counted === expectedQty) {
    return { countedQty: counted, status: null }
  }
  return { countedQty: counted, status: 'problem' }
}

export function inventoryLocationSettled(review: InventoryLocationReview | undefined): boolean {
  return review?.status === 'ok'
}

export function allInventoryLocationsSettled(lineReview: ShellForwardLineReview): boolean {
  if (lineReview.inventoryLocations.length === 0) return true
  return lineReview.inventoryLocations.every((loc) =>
    inventoryLocationSettled(lineReview.inventoryLocationReviews[storageLocationRowKey(loc)]),
  )
}

export function buildInitialInventoryLocationReviews(
  locations: MiniInventoryLocationRow[],
): Record<string, InventoryLocationReview> {
  const out: Record<string, InventoryLocationReview> = {}
  for (const loc of locations) {
    out[storageLocationRowKey(loc)] = defaultInventoryLocationReview(loc.qty)
  }
  return out
}

export function shortfallQty(expected: number, counted: number): number {
  return Math.max(0, expected - counted)
}

export function surplusQty(expected: number, counted: number): number {
  return Math.max(0, counted - expected)
}

export function defaultLineReview(expectedQty: number): ShellForwardLineReview {
  return {
    status: null,
    resolution: null,
    note: '',
    missingQty: null,
    countedQty: expectedQty,
    doReplenishAfterLoss: false,
    replenishQty: null,
    inventoryPhase: 'none',
    inventoryLocationReviews: {},
    inventoryLocations: [],
  }
}

/** Übertrag-Modal: leer starten (0 gezählt, nichts bestätigt). */
export function emptyShellForwardLineReview(): ShellForwardLineReview {
  return {
    status: null,
    resolution: null,
    note: '',
    missingQty: null,
    countedQty: 0,
    doReplenishAfterLoss: false,
    replenishQty: null,
    inventoryPhase: 'none',
    inventoryLocationReviews: {},
    inventoryLocations: [],
  }
}

export function buildEmptyShellForwardLineReviews(
  lines: ShellForwardCheckLine[],
): Record<string, ShellForwardLineReview> {
  const out: Record<string, ShellForwardLineReview> = {}
  for (const line of lines) {
    out[line.key] = emptyShellForwardLineReview()
  }
  return out
}

/** Übertrag-Modal: Ist = Soll vorfüllen, Bestätigung per ✓. */
export function buildDefaultShellForwardLineReviews(
  lines: ShellForwardCheckLine[],
): Record<string, ShellForwardLineReview> {
  const out: Record<string, ShellForwardLineReview> = {}
  for (const line of lines) {
    out[line.key] = defaultLineReview(line.quantity)
  }
  return out
}

export function applyCountedQtyToReview(
  review: ShellForwardLineReview,
  expectedQty: number,
  isExtra: boolean,
  options?: { explicitOkOnly?: boolean },
): ShellForwardLineReview {
  const counted = Math.max(0, Math.floor(Number(review.countedQty) || 0))
  if (isExtra) {
    if (counted <= 0) {
      return { ...review, countedQty: counted, status: null, resolution: null }
    }
    if (counted === expectedQty) {
      const keepExplicitOk = options?.explicitOkOnly && review.status === 'ok'
      return {
        ...review,
        countedQty: counted,
        status: keepExplicitOk ? 'ok' : options?.explicitOkOnly ? null : 'ok',
        resolution: null,
        missingQty: null,
        inventoryPhase: 'none',
      }
    }
    return {
      ...review,
      countedQty: counted,
      status: 'problem',
      resolution: 'extra',
      missingQty: surplusQty(expectedQty, counted) || 1,
      inventoryPhase: 'none',
    }
  }
  if (counted === expectedQty) {
    const keepExplicitOk = options?.explicitOkOnly && review.status === 'ok'
    return {
      ...review,
      countedQty: counted,
      status: keepExplicitOk ? 'ok' : options?.explicitOkOnly ? null : 'ok',
      resolution: null,
      missingQty: null,
      doReplenishAfterLoss: false,
      inventoryPhase: 'none',
    }
  }
  if (counted > expectedQty) {
    const sur = surplusQty(expectedQty, counted)
    return {
      ...review,
      countedQty: counted,
      status: 'problem',
      resolution: 'extra',
      missingQty: sur,
      note: review.note,
      inventoryPhase: 'none',
    }
  }
  const miss = shortfallQty(expectedQty, counted)
  return {
    ...review,
    countedQty: counted,
    status: 'problem',
    resolution: review.resolution === 'not_taken' ? 'not_taken' : review.resolution,
    missingQty: review.missingQty != null && review.missingQty >= 1 ? review.missingQty : miss,
    inventoryPhase:
      review.inventoryPhase === 'done' ||
      review.inventoryPhase === 'skipped' ||
      review.inventoryPhase === 'active'
        ? review.inventoryPhase
        : 'none',
  }
}

/** Regalplätze und andere Kisten für Mini-Inventur (aktuelle Kiste ausgeschlossen). */
export function buildMiniInventoryLocations(
  direct: MaterialStorageLocationRow[],
  viaCombo: MaterialStorageViaComboBlock[],
  excludeContainerBatchId: string | null,
): MiniInventoryLocationRow[] {
  const exclude = (excludeContainerBatchId ?? '').trim()
  const out: MiniInventoryLocationRow[] = []

  for (const loc of direct) {
    if (loc.qty <= 0) continue
    const containerBatchId = (loc.container_batch_id ?? '').trim()
    if (exclude !== '' && containerBatchId !== '' && containerBatchId === exclude) continue

    const inContainer = Boolean((loc.container_caption || '').trim()) || containerBatchId !== ''
    out.push({
      ...loc,
      location_kind: inContainer ? 'container' : 'loose',
    })
  }

  for (const block of viaCombo) {
    const stored = block.stored_qty_in_container ?? 0
    const containerBatchId = (block.parent_linked_container_batch_id ?? '').trim()
    if (stored <= 0 || containerBatchId === '') continue
    if (exclude !== '' && containerBatchId === exclude) continue

    const parentName = (block.parent_name || '').trim() || 'Kombi'
    out.push({
      rack_id: null,
      slot_id: null,
      rack_name: null,
      slot_name: null,
      storage_address_name: null,
      location_label: null,
      qty: stored,
      batch_id: `combo:${block.combo_component_id ?? block.parent_material_id}`,
      container_batch_id: containerBatchId,
      container_caption: parentName,
      location_kind: 'combo',
    })
  }

  return out
}

export function miniInventoryLooseLocations(
  locations: MiniInventoryLocationRow[],
): MiniInventoryLocationRow[] {
  return locations.filter((loc) => loc.location_kind === 'loose')
}

export function miniInventoryContainerLocations(
  locations: MiniInventoryLocationRow[],
): MiniInventoryLocationRow[] {
  return locations.filter((loc) => loc.location_kind === 'container' || loc.location_kind === 'combo')
}

export function buildPackCrateCheckLinesPayload(
  lines: ShellForwardCheckLine[],
  reviews: Record<string, ShellForwardLineReview>,
  historyReplenishByKey: Record<string, boolean>,
): PackCrateCheckLinePayload[] {
  return lines.map((line) => {
    const review = reviews[line.key]
    let status: PackCrateCheckLineStatus = 'ok'
    if (historyReplenishByKey[line.key]) {
      status = 'ok'
    } else if (review?.status === 'problem' && review.resolution) {
      status = review.resolution
    }
    const missingQty = review?.missingQty ?? null
    let replenishQty: number | null = null
    if (status === 'replenish') {
      replenishQty = missingQty != null && missingQty >= 1 ? missingQty : 1
    } else if (
      status === 'loss' &&
      review?.doReplenishAfterLoss &&
      (review.replenishQty ?? 0) >= 1
    ) {
      replenishQty = review.replenishQty
    }
    const noteParts: string[] = []
    if (review?.note?.trim()) noteParts.push(review.note.trim())
    if (review && review.countedQty !== line.quantity) {
      noteParts.push(`Ist ${review.countedQty} / Soll ${line.quantity}`)
    }
    if (review?.inventoryPhase === 'done' && allInventoryLocationsSettled(review)) {
      const locNotes: string[] = []
      for (const loc of review.inventoryLocations) {
        const key = storageLocationRowKey(loc)
        const lr = review.inventoryLocationReviews[key]
        if (!lr) continue
        locNotes.push(`${formatStorageLocationPlaceLabel(loc)}: Ist ${lr.countedQty}/${loc.qty}`)
      }
      noteParts.push(
        locNotes.length > 0
          ? `Mini-Inventur: ${locNotes.join('; ')}`
          : 'Mini-Inventur: Plätze geprüft',
      )
    }
    const countedQty =
      review && review.countedQty != null ? Math.max(0, Math.floor(review.countedQty)) : line.quantity

    return {
      line_key: line.key,
      material_item_id: line.materialItemId,
      material_name: line.materialName,
      expected_qty: line.quantity,
      counted_qty: countedQty,
      status,
      missing_qty: missingQty,
      note: noteParts.length > 0 ? noteParts.join(' — ') : null,
      replenish_qty: replenishQty,
    }
  })
}
