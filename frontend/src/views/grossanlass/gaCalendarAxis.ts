import {
  calendarColumns,
  calendarWindow,
  dateToYmd,
  spanningMonthWindow,
  type GaCalendarColumn,
  type GaCalendarScale,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'

/** Neutral month divider — not indigo/violet occupancy bars. */
export const GA_CALENDAR_MONTH_LINE = '#9ca3af'

export type GaAxisColumn = GaCalendarColumn & { monthStart: boolean; ymd: string }

export function withAxisMeta(column: GaCalendarColumn, scale: GaCalendarScale): GaAxisColumn {
  const date = new Date(column.startMs)
  const ymd = dateToYmd(date)
  return {
    ...column,
    // Calendar day 01 only — avoid timezone/ms drift on the month divider.
    monthStart: scale !== 'day' && ymd.endsWith('-01'),
    ymd,
  }
}

export function monthBandsFromColumns(
  columns: GaAxisColumn[],
  locale: string,
): { key: string; label: string; days: number }[] {
  const bands: { key: string; label: string; days: number }[] = []
  for (const column of columns) {
    const date = new Date(column.startMs)
    const key = `${date.getFullYear()}-${date.getMonth()}`
    const last = bands[bands.length - 1]
    if (last && last.key === key) {
      last.days += 1
      continue
    }
    bands.push({
      key,
      label: date.toLocaleDateString(locale, { month: 'short' }).replace(/\.$/, ''),
      days: 1,
    })
  }
  return bands
}

export function resolveCalendarWindow(
  scale: GaCalendarScale,
  anchor: Date,
  spanDates: Date[],
  spanMonths: boolean,
): { start: Date; end: Date } {
  if (scale === 'month' && spanMonths) {
    const span = spanningMonthWindow(spanDates)
    if (span) return span
  }
  return calendarWindow(scale, anchor)
}
