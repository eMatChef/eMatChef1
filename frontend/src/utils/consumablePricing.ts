import type { MaterialBatch } from '@/api/materials'
import {
  sumAcquisitionBasisFromBatches,
  sumAcquisitionPieceCountFromBatches,
  roundChfToFiveRappen,
  formatChfFiveRappenString,
} from '@/utils/rentalPriceAmortization'

export interface BatchPurchaseHistoryRow {
  id: string
  acquired_on: string
  qty: number
  unit_price: number | null
}

function parseBatchUnitPrice(raw: string | null | undefined): number | null {
  if (raw == null || raw === '') return null
  const n = parseFloat(String(raw).replace(',', '.'))
  return Number.isFinite(n) && n > 0 ? n : null
}

/** Chargen mit Einkaufspreis, neueste zuerst. */
export function batchPurchaseHistory(batches: MaterialBatch[] | null | undefined): BatchPurchaseHistoryRow[] {
  if (!Array.isArray(batches)) return []
  const rows: BatchPurchaseHistoryRow[] = []
  for (const b of batches) {
    const unit = parseBatchUnitPrice(b.unit_price)
    if (unit == null) continue
    rows.push({
      id: b.id,
      acquired_on: b.acquired_on,
      qty: b.qty ?? 0,
      unit_price: unit,
    })
  }
  return rows.sort((a, b) => new Date(b.acquired_on).getTime() - new Date(a.acquired_on).getTime())
}

/** Gewichteter Ø-EK pro Stück aus aktiven Chargen. */
export function averagePurchaseUnitFromBatches(batches: MaterialBatch[] | null | undefined): number | null {
  const basis = sumAcquisitionBasisFromBatches(batches)
  const pieces = sumAcquisitionPieceCountFromBatches(batches)
  if (basis == null || pieces == null || pieces <= 0) return null
  return roundChfToFiveRappen(basis / pieces)
}

/** Neueste Charge mit Stückpreis. */
export function latestPurchaseUnitFromBatches(batches: MaterialBatch[] | null | undefined): number | null {
  const history = batchPurchaseHistory(batches)
  return history[0]?.unit_price ?? null
}

/** Preisverlauf älteste → neueste (für Anzeige). */
export function purchasePriceTrendLabel(batches: MaterialBatch[] | null | undefined): string | null {
  const history = [...batchPurchaseHistory(batches)].reverse()
  const prices = history.map((r) => r.unit_price).filter((p): p is number => p != null)
  if (prices.length < 2) return null
  const first = prices[0]!
  const last = prices[prices.length - 1]!
  return `${formatChfFiveRappenString(first)} → ${formatChfFiveRappenString(last)}`
}

/** Verkaufspreis-Vorschlag: Referenz-EK × (1 + Aufschlag %). */
export function suggestSalePriceFromReference(
  referenceUnitChf: number | null | undefined,
  markupPercent: number,
): number | null {
  const ref = referenceUnitChf
  if (ref == null || !Number.isFinite(ref) || ref <= 0) return null
  const markup = Number(markupPercent)
  if (!Number.isFinite(markup)) return null
  return roundChfToFiveRappen(ref * (1 + markup / 100))
}

/** Zusatz = Aufschlag auf Basis (z. B. Verkaufspreis) — für externe Verrechnung. */
export function suggestMarkupSupplement(
  baseUnitChf: number | null | undefined,
  markupPercent: number,
): number | null {
  const total = suggestSalePriceFromReference(baseUnitChf, markupPercent)
  const base = baseUnitChf
  if (total == null || base == null || base <= 0) return null
  const extra = total - base
  return extra > 0 ? roundChfToFiveRappen(extra) : null
}

export function formatChfDisplay(amount: number | null | undefined): string {
  if (amount == null || !Number.isFinite(amount)) return '–'
  return formatChfFiveRappenString(amount)
}
