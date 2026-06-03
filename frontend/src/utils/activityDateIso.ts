import { startOfLocalDay } from '@/utils/swissMovableFeasts'

/** ISO-Datum `yyyy-mm-dd` — Schlüssel für VDatePicker-`events`. */
export function toIsoDateKey(d: Date): string {
  const x = startOfLocalDay(d)
  const y = x.getFullYear()
  const m = String(x.getMonth() + 1).padStart(2, '0')
  const day = String(x.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function formatActivityDateDe(d: Date | null | undefined): string {
  if (!d || !Number.isFinite(d.getTime())) return ''
  return d.toLocaleDateString('de-CH', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function pad2(n: number): string {
  return String(n).padStart(2, '0')
}

/** Tag mit Punkt, z. B. «15.» */
function formatActivityDayDot(d: Date): string {
  return `${pad2(d.getDate())}.`
}

/** Kurz: dd.mm.yy */
function formatActivityDayMonthYearShort(d: Date): string {
  return `${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${pad2(d.getFullYear() % 100)}`
}

/** Tag + Monat mit Punkt, z. B. «15.06.» */
function formatActivityDayMonth(d: Date): string {
  return `${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.`
}

/**
 * Zeitraum-Anzeige (de-CH).
 * Gleicher Monat + Jahr: «15. – 28.06.26» · gleiches Jahr: «15.06. – 28.07.26» · sonst voll.
 */
export function formatActivityDateRangeDe(range: [Date, Date] | null | undefined): string {
  if (!range?.[0] || !range[1]) return ''
  const a = range[0]
  const b = range[1]
  const [start, end] = a.getTime() <= b.getTime() ? [a, b] : [b, a]

  if (start.getTime() === end.getTime()) {
    return formatActivityDateDe(start)
  }

  const sameYear = start.getFullYear() === end.getFullYear()
  const sameMonth = sameYear && start.getMonth() === end.getMonth()

  if (sameMonth) {
    return `${formatActivityDayDot(start)} – ${formatActivityDayMonthYearShort(end)}`
  }
  if (sameYear) {
    return `${formatActivityDayMonth(start)} – ${formatActivityDayMonthYearShort(end)}`
  }
  return `${formatActivityDateDe(start)} – ${formatActivityDateDe(end)}`
}
