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

export function formatActivityDateRangeDe(range: [Date, Date] | null | undefined): string {
  if (!range?.[0] || !range[1]) return ''
  return `${formatActivityDateDe(range[0])} – ${formatActivityDateDe(range[1])}`
}
