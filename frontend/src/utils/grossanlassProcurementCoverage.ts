import type { GrossanlassProcurementLine } from '@/api/grossanlassProcurement'

const ORDERED_STATUSES = new Set(['bestellt', 'teilweise_erhalten', 'erhalten'])

export function procurementLoanedQty(line: GrossanlassProcurementLine): number {
  return Math.max(0, line.quantity_loaned ?? 0)
}

export function procurementIsOrdered(line: GrossanlassProcurementLine): boolean {
  return ORDERED_STATUSES.has(line.status)
}

export function procurementOrderedQty(line: GrossanlassProcurementLine): number {
  if (!procurementIsOrdered(line)) return 0
  return Math.max(0, line.quantity)
}

export function procurementCoveredQty(
  line: GrossanlassProcurementLine,
  otherTaken: number,
  hereTaken = 0,
): number {
  const ordered = procurementOrderedQty(line)
  if (ordered > 0) return ordered
  return otherTaken + hereTaken
}

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
