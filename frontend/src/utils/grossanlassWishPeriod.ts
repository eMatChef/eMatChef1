import type { CalendarPeriodLabel, DepartmentCalendarPeriod } from '@/api/calendarPeriods'

const TWO_MINUTES_MS = 2 * 60 * 1000

export function mapWishPhaseChoiceToCalendarLabel(choice: string): CalendarPeriodLabel | null {
  const n = choice.trim().toLowerCase()
  if (!n) return null
  if (n.includes('vor dem') || n.includes('before') || n.includes('aufbau')) return 'aufbau'
  if (n.includes('nach dem') || n.includes('after') || n.includes('abbau')) return 'abbau'
  if (
    n.includes('am anlass')
    || n.includes('during')
    || n.includes('grossanlass')
    || n.includes('durchführung')
    || n.includes('durchfuehrung')
  ) {
    return 'grossanlass'
  }
  return null
}

export function unionCalendarPeriods(
  periods: DepartmentCalendarPeriod[],
  labels: CalendarPeriodLabel[],
): { from: string; to: string } | null {
  if (labels.length === 0) return null
  const wanted = new Set(labels)
  const eventYear = latestEventYear(periods)
  const matches = periods.filter((period) => {
    if (!wanted.has(period.label)) return false
    if (eventYear && period.start_date.slice(0, 4) !== eventYear) return false
    return true
  })
  const use = matches.length > 0
    ? matches
    : periods.filter((period) => wanted.has(period.label))
  if (use.length === 0) return null

  let from: string | null = null
  let to: string | null = null
  for (const period of use) {
    const start = `${period.start_date}T${clock(period.start_time, '00:00')}:00`
    const end = `${period.end_date}T${clock(period.end_time, '23:45')}:00`
    if (!from || start < from) from = start
    if (!to || end > to) to = end
  }
  return from && to ? { from, to } : null
}

export function calendarRangeForPhaseChoice(
  choice: string,
  periods: DepartmentCalendarPeriod[],
): { from: string; to: string } | null {
  const label = mapWishPhaseChoiceToCalendarLabel(choice)
  if (!label) return null
  return unionCalendarPeriods(periods, [label])
}

export function isWishPhaseSelectField(field: {
  custom_type?: string | null
  options?: { choices?: string[]; multiple?: boolean } | null
}): boolean {
  if (field.custom_type !== 'select') return false
  return (field.options?.choices || []).some((choice) => mapWishPhaseChoiceToCalendarLabel(choice) !== null)
}

export function wishPeriodMatchesRange(
  fromIso?: string | null,
  toIso?: string | null,
  expected?: { from: string; to: string } | null,
): boolean {
  if (!fromIso || !toIso || !expected) return false
  const actualFrom = Date.parse(fromIso)
  const actualTo = Date.parse(toIso)
  const expectedFrom = Date.parse(expected.from)
  const expectedTo = Date.parse(expected.to)
  if (![actualFrom, actualTo, expectedFrom, expectedTo].every(Number.isFinite)) return false
  const toleranceMs = 60 * 1000
  return Math.abs(actualFrom - expectedFrom) < toleranceMs && Math.abs(actualTo - expectedTo) < toleranceMs
}

export function wishPeriodLooksLikeSubmitTime(
  fromIso?: string | null,
  toIso?: string | null,
  createdAt?: string | null,
): boolean {
  if (!fromIso || !toIso) return false
  const from = Date.parse(fromIso)
  const to = Date.parse(toIso)
  if (!Number.isFinite(from) || !Number.isFinite(to)) return false
  if (Math.abs(to - from) >= TWO_MINUTES_MS) return false
  if (!createdAt) return true
  const created = Date.parse(createdAt)
  if (!Number.isFinite(created)) return true
  return Math.abs(from - created) < 24 * 60 * 60 * 1000
}

export type WishNeedPeriodSource = {
  valid_from?: string | null
  valid_to?: string | null
  created_at?: string | null
  custom_values?: Record<string, unknown> | null
}

export function phaseLabelsFromCustomValues(
  customValues?: Record<string, unknown> | null,
): CalendarPeriodLabel[] {
  const labels: CalendarPeriodLabel[] = []
  const seen = new Set<string>()
  for (const raw of Object.values(customValues || {})) {
    const choices = Array.isArray(raw) ? raw : [raw]
    for (const choice of choices) {
      if (typeof choice !== 'string' && typeof choice !== 'number') continue
      const label = mapWishPhaseChoiceToCalendarLabel(String(choice))
      if (label && !seen.has(label)) {
        seen.add(label)
        labels.push(label)
      }
    }
  }
  return labels
}

export function wishPeriodLooksUnreliable(
  fromIso?: string | null,
  toIso?: string | null,
  createdAt?: string | null,
  periods: DepartmentCalendarPeriod[] = [],
): boolean {
  if (!fromIso || !toIso) return true
  if (wishPeriodLooksLikeSubmitTime(fromIso, toIso, createdAt)) return true
  const eventYear = latestEventYear(periods)
  if (!eventYear) return false
  return fromIso.slice(0, 4) !== eventYear && toIso.slice(0, 4) !== eventYear
}

/** Live Bedarf: Vor/Am/Nach (Fixe Daten), unless the stored range is a real custom date. */
export function resolveWishNeedPeriod(
  wish: WishNeedPeriodSource | null | undefined,
  periods: DepartmentCalendarPeriod[] = [],
): { from: string; to: string } | null {
  if (!wish) return null
  const phaseRange = unionCalendarPeriods(periods, phaseLabelsFromCustomValues(wish.custom_values))
  const storedFrom = wish.valid_from || ''
  const storedTo = wish.valid_to || ''
  const unreliable = wishPeriodLooksUnreliable(storedFrom, storedTo, wish.created_at, periods)
  if (unreliable) return phaseRange
  if (storedFrom && storedTo) return { from: storedFrom, to: storedTo }
  return phaseRange
}

function latestEventYear(periods: DepartmentCalendarPeriod[]): string | null {
  let latest: string | null = null
  for (const period of periods) {
    if (period.label !== 'grossanlass') continue
    if (!latest || period.start_date > latest) latest = period.start_date
  }
  return latest ? latest.slice(0, 4) : null
}

function clock(raw: string | undefined, fallback: string): string {
  const value = (raw || fallback).slice(0, 5)
  return /^\d{2}:\d{2}$/.test(value) ? value : fallback
}
