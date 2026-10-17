import type { GrossanlassProcurementLine } from '@/api/grossanlassProcurement'

const ORDERED_STATUSES = new Set(['bestellt', 'teilweise_erhalten', 'erhalten'])

export function procurementLoanedQty(line: GrossanlassProcurementLine): number {
  return Math.max(0, line.quantity_loaned ?? 0)
}

export function procurementIsOrdered(line: GrossanlassProcurementLine): boolean {
  return ORDERED_STATUSES.has(line.status)
}

/**
 * Bestellte Menge: Summe der Kauf-Chargen aller aktiven Bestellungen (Server rechnet). Nur Payloads ohne dieses Feld
 * (ältere Antworten) fallen auf die ganze Positionsmenge zurück, sobald die Position bestellt ist.
 */
export function procurementOrderedQty(line: GrossanlassProcurementLine): number {
  if (line.quantity_ordered != null) return Math.max(0, line.quantity_ordered)
  if (!procurementIsOrdered(line)) return 0
  return Math.max(0, line.quantity)
}

/**
 * Gedeckte Menge einschliesslich noch nicht gespeicherter Zusagen im Formular (`hereTaken`).
 * Bestellungen, Leihe und Spende decken zusammen; eine Offerte allein deckt nichts.
 */
export function procurementCoveredQty(
  line: GrossanlassProcurementLine,
  otherTaken: number,
  hereTaken = 0,
): number {
  return procurementOrderedQty(line) + otherTaken + hereTaken
}

/** Offene Menge; negativ bei Überdeckung. */
export function procurementCoverageOpen(
  line: GrossanlassProcurementLine,
  otherTaken: number,
  hereTaken = 0,
): number {
  return line.quantity - procurementCoveredQty(line, otherTaken, hereTaken)
}

export function procurementWishBreakdown(
  line: GrossanlassProcurementLine,
  t: (key: string, values?: Record<string, unknown>) => string,
): string {
  if (line.source_wishes.length < 2) return ''
  return line.source_wishes
    .map((wish) => t('grossanlass.beschaffung.zusagen.qtyLabel', {
      count: wish.quantity,
      name: wish.label,
    }))
    .join(' + ')
}
