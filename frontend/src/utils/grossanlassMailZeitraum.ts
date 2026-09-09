import type { DepartmentCalendarPeriod } from '@/api/calendarPeriods'

const PHASE_ORDER = ['aufbau', 'grossanlass', 'abbau'] as const

export function formatCalendarPeriodRange(period: Pick<DepartmentCalendarPeriod, 'start_date' | 'end_date'>): string {
  const from = formatIsoDateDe(period.start_date)
  const to = formatIsoDateDe(period.end_date)
  if (!from) return to
  if (!to || from === to) return from
  return `${from}–${to}`
}

export function suggestZeitraumTextFromPeriods(
  periods: DepartmentCalendarPeriod[],
  labels: { aufbau: string; grossanlass: string; abbau: string },
  closing: string,
): string {
  const lines: string[] = []
  for (const key of PHASE_ORDER) {
    const row = periods.find((period) => period.label === key)
    if (!row) continue
    const range = formatCalendarPeriodRange(row)
    if (!range) continue
    lines.push(`${labels[key]}: ${range}`)
  }
  if (lines.length === 0) return ''
  const end = closing.trim()
  if (end) lines.push(end)
  return lines.join('\n')
}

function formatIsoDateDe(iso: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso.trim())
  if (!match) return ''
  return `${Number(match[3])}.${Number(match[2])}.${match[1]}`
}
