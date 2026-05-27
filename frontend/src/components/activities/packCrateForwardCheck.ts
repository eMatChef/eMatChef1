import type { MaterialStorageLocationRow, MaterialStorageViaComboBlock } from '@/api/materials'
import type { PackCrateCheckLinePayload, PackCrateCheckLineStatus } from '@/api/activityPackCrateCheck'

export type ShellForwardResolution =
  | null
  | 'replenish'
  | 'not_taken'
  | 'loss'
  | 'repair'
  | 'extra'
  | 'return_surplus'
  | 'found_elsewhere'

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
  /** Überschuss: Stk. zurück ins Lager */
  returnSurplusQty: number | null
  /** Werkstatt-Aufgabe (Inspektion) für Lager-Kontrolle */
  createSurplusInspectionTask: boolean
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
  /** Erwartete Seriennummer(n) in der Kiste — zur Sichtprüfung */
  serialHint?: string | null
}

/** Anzeigetext für Seriennummer / Batch-Label (Kistencheck, Mini-Inventur). */
export function formatBatchSerialHint(
  serialNumber?: string | null,
  batchLabel?: string | null,
): string | null {
  const sn = (serialNumber ?? '').trim()
  const label = (batchLabel ?? '').trim()
  if (sn && label && label !== sn) return `${sn} – ${label}`
  return sn || label || null
}

export function shellForwardLineKey(subsectionKey: string, lineId: string): string {
  return `${subsectionKey}:${lineId}`
}

/** Zusatz-Zeilen: kein Soll — nur Ist zählen (Überschuss wenn Ist > 0). */
export function shellForwardExpectedQty(isExtra: boolean, lineQuantity: number): number {
  return isExtra ? 0 : Math.max(0, Math.floor(Number(lineQuantity)) || 0)
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

function storageLocationPlaceWithoutSerial(loc: MiniInventoryLocationRow): string {
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

/** Ortsbezeichnung ohne Stückzahl (Soll steht an den Mini-Zählern); inkl. Seriennummer wenn vorhanden. */
export function formatStorageLocationPlaceLabel(loc: MiniInventoryLocationRow): string {
  const place = storageLocationPlaceWithoutSerial(loc)
  const serial = formatBatchSerialHint(loc.serial_number, loc.batch_label)
  if (!serial) return place
  return place !== '—' ? `SN ${serial} · ${place}` : `SN ${serial}`
}

export function defaultInventoryLocationReview(expectedQty: number): InventoryLocationReview {
  return { countedQty: expectedQty, status: null }
}

export function applyInventoryLocationCounted(
  review: InventoryLocationReview,
  expectedQty: number,
): InventoryLocationReview {
  const counted = Math.max(0, Math.floor(Number(review.countedQty)) || 0)
  if (review.status === 'ok' && counted >= expectedQty) {
    return { countedQty: counted, status: 'ok' }
  }
  if (review.status === 'ok' && counted < expectedQty) {
    return { countedQty: counted, status: 'problem' }
  }
  if (counted >= expectedQty) {
    return { countedQty: counted, status: null }
  }
  return { countedQty: counted, status: 'problem' }
}

/** Überschuss an bestätigtem Ort (Ist > Soll) — erklärt fehlende Menge in der Kiste. */
export function inventorySurplusAtLocation(countedQty: number, locationSoll: number): number {
  const counted = Math.max(0, Math.floor(Number(countedQty)) || 0)
  return Math.max(0, counted - locationSoll)
}

export function inventoryFoundQtyFromReviews(
  locations: MiniInventoryLocationRow[],
  reviews: Record<string, InventoryLocationReview>,
): number {
  let found = 0
  for (const loc of locations) {
    const lr = reviews[storageLocationRowKey(loc)]
    if (lr?.status !== 'ok') continue
    found += inventorySurplusAtLocation(lr.countedQty, loc.qty)
  }
  return found
}

export function inventoryCoversShortfall(
  crateShortfall: number,
  locations: MiniInventoryLocationRow[],
  reviews: Record<string, InventoryLocationReview>,
): boolean {
  const miss = Math.max(0, Math.floor(crateShortfall) || 0)
  if (miss < 1) return false
  return inventoryFoundQtyFromReviews(locations, reviews) >= miss
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

/** Gruppe/Leiter: Abweichung automatisch als «nicht mitgenommen» / «Extra» protokollieren. */
export function applyGroupAutoResolution(
  review: ShellForwardLineReview,
  expectedQty: number,
): ShellForwardLineReview {
  if (review.status !== 'problem') return review
  const miss = shortfallQty(expectedQty, review.countedQty)
  const sur = surplusQty(expectedQty, review.countedQty)
  if (miss > 0) {
    return {
      ...review,
      resolution: 'not_taken',
      missingQty: miss,
      inventoryPhase: 'none',
      doReplenishAfterLoss: false,
      replenishQty: null,
    }
  }
  if (sur > 0) {
    return {
      ...review,
      resolution: 'extra',
      missingQty: sur,
      inventoryPhase: 'none',
    }
  }
  return review
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
    returnSurplusQty: null,
    createSurplusInspectionTask: true,
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
    returnSurplusQty: null,
    createSurplusInspectionTask: true,
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
    /** Soll 0 / Ist 0: «nichts Zusätzliches» — nur per ✓ bestätigen (nicht als Fehlmenge). */
    if (expectedQty <= 0 && counted <= 0) {
      const keepExplicitOk = options?.explicitOkOnly && review.status === 'ok'
      return {
        ...review,
        countedQty: 0,
        status: keepExplicitOk ? 'ok' : options?.explicitOkOnly ? null : 'ok',
        resolution: null,
        missingQty: null,
        inventoryPhase: 'none',
      }
    }
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
      resolution: review.resolution === 'return_surplus' ? 'return_surplus' : null,
      missingQty: surplusQty(expectedQty, counted) || 1,
      returnSurplusQty:
        review.returnSurplusQty != null && review.returnSurplusQty >= 1
          ? review.returnSurplusQty
          : surplusQty(expectedQty, counted) || 1,
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
      resolution: review.resolution === 'return_surplus' ? 'return_surplus' : null,
      missingQty: sur,
      returnSurplusQty:
        review.returnSurplusQty != null && review.returnSurplusQty >= 1 ? review.returnSurplusQty : sur,
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
    } else if (review?.resolution === 'found_elsewhere') {
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
    } else if (status === 'return_surplus') {
      replenishQty =
        review?.returnSurplusQty != null && review.returnSurplusQty >= 1
          ? review.returnSurplusQty
          : missingQty != null && missingQty >= 1
            ? missingQty
            : 1
    }
    const noteParts: string[] = []
    if (review?.note?.trim()) noteParts.push(review.note.trim())
    if (line.serialHint?.trim()) {
      noteParts.push(`Seriennummer: ${line.serialHint.trim()}`)
    }
    if (review && review.countedQty !== line.quantity) {
      noteParts.push(`Ist ${review.countedQty} / Soll ${line.quantity}`)
    }
    if (review?.inventoryPhase === 'done' && allInventoryLocationsSettled(review)) {
      const locNotes: string[] = []
      for (const loc of review.inventoryLocations) {
        const key = storageLocationRowKey(loc)
        const lr = review.inventoryLocationReviews[key]
        if (!lr) continue
        const sur = inventorySurplusAtLocation(lr.countedQty, loc.qty)
        const place = formatStorageLocationPlaceLabel(loc)
        if (lr.status === 'ok' && sur > 0) {
          locNotes.push(`${place}: +${sur} Stk. (Ist ${lr.countedQty}, Soll ${loc.qty})`)
        } else {
          locNotes.push(`${place}: Ist ${lr.countedQty}/${loc.qty}`)
        }
      }
      const found = inventoryFoundQtyFromReviews(
        review.inventoryLocations,
        review.inventoryLocationReviews,
      )
      const invPrefix =
        review.resolution === 'found_elsewhere'
          ? `Mini-Inventur: fehlende Menge woanders gefunden (${found} Stk.) — `
          : 'Mini-Inventur: '
      noteParts.push(
        locNotes.length > 0
          ? `${invPrefix}${locNotes.join('; ')}`
          : review.resolution === 'found_elsewhere'
            ? `${invPrefix}Plätze geprüft`
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
      create_inspection_task:
        status === 'return_surplus' ? review?.createSurplusInspectionTask !== false : null,
    }
  })
}
