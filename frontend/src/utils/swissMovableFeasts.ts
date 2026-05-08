import type { DatePickerMarker } from '@vuepic/vue-datepicker'

/** Lokaler Tagesbeginn */
export function startOfLocalDay(d: Date): Date {
  const x = new Date(d.getTime())
  x.setHours(0, 0, 0, 0)
  return x
}

export function startOfToday(): Date {
  return startOfLocalDay(new Date())
}

function addDays(d: Date, n: number): Date {
  const x = new Date(d.getTime())
  x.setDate(x.getDate() + n)
  return x
}

/**
 * Ostersonntag (gregorianisch, Oudin / gängige JS-Variante).
 */
export function easterSunday(year: number): Date {
  const f = Math.floor
  const G = year % 19
  const C = f(year / 100)
  const H = (C - f(C / 4) - f((8 * C + 13) / 25) + 19 * G + 15) % 30
  const I = H - f(H / 28) * (1 - f(29 / (H + 1)) * f((21 - G) / 11))
  const J = (year + f(year / 4) + I + 2 - C + f(C / 4)) % 7
  const L = I - J
  const month = 3 + f((L + 40) / 44)
  const day = L + 28 - 31 * f(month / 4)
  return new Date(year, month - 1, day)
}

/** Karfreitag bis Ostermontag (CH üblich) */
export function osternLongWeekendRange(year: number): [Date, Date] {
  const es = startOfLocalDay(easterSunday(year))
  return [addDays(es, -2), addDays(es, 1)]
}

/**
 * Christi Himmelfahrt (Do, 39 Tage nach Ostersonntag) bis einschließlich Sonntag
 * (typisches verlängertes Wochenende).
 */
export function auffahrtRange(year: number): [Date, Date] {
  const es = startOfLocalDay(easterSunday(year))
  const thu = addDays(es, 39)
  const sun = addDays(es, 42)
  return [thu, sun]
}

/** Pfingstsonntag–Pfingstmontag */
export function pfingstenRange(year: number): [Date, Date] {
  const es = startOfLocalDay(easterSunday(year))
  return [addDays(es, 49), addDays(es, 50)]
}

/**
 * Erster noch (teilweise) zukünftiger Zeitraum: wenn der Beginn in der Vergangenheit liegt,
 * wird auf heute begrenzt (passend zu minDate im Datepicker).
 */
export function nextFutureHolidayRange(build: (year: number) => [Date, Date]): [Date, Date] {
  const today = startOfToday()
  const y0 = today.getFullYear()
  for (let y = y0; y <= y0 + 4; y++) {
    const [rawA, rawB] = build(y)
    const a = startOfLocalDay(rawA)
    const b = startOfLocalDay(rawB)
    if (b < today) continue
    const start = a < today ? today : a
    return [start, b]
  }
  const [a, b] = build(y0 + 5)
  return [startOfLocalDay(a), startOfLocalDay(b)]
}

export interface SwissHolidayCalendarDay {
  date: Date
  /** Kurztext für Kalender-Tooltip (CH: Bundesfeiertage + bewegliche Feiertage) */
  label: string
}

function dayKey(d: Date): string {
  const x = startOfLocalDay(d)
  const y = x.getFullYear()
  const m = x.getMonth() + 1
  const day = x.getDate()
  return `${y}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

function mergeHolidayLabel(map: Map<string, string>, d: Date, label: string): void {
  const k = dayKey(d)
  if (!map.has(k)) map.set(k, label)
}

/**
 * Tage mit Feiertags-Hinweis für den Datepicker (Marker).
 * Umfasst eidgenössische Fixfeiertage sowie bewegliche Feiertage wie in den Presets.
 */
export function swissHolidayCalendarDays(minYear: number, maxYear: number): SwissHolidayCalendarDay[] {
  const map = new Map<string, string>()
  const add = (d: Date, label: string) => mergeHolidayLabel(map, d, label)

  for (let y = minYear; y <= maxYear; y++) {
    add(startOfLocalDay(new Date(y, 0, 1)), 'Neujahr')
    add(startOfLocalDay(new Date(y, 4, 1)), 'Tag der Arbeit')
    add(startOfLocalDay(new Date(y, 7, 1)), 'Bundesfeiertag')
    add(startOfLocalDay(new Date(y, 11, 25)), 'Weihnachten')
    add(startOfLocalDay(new Date(y, 11, 26)), 'Stephanstag')

    const es = startOfLocalDay(easterSunday(y))
    add(addDays(es, -2), 'Karfreitag')
    add(addDays(es, -1), 'Karsamstag')
    add(es, 'Ostersonntag')
    add(addDays(es, 1), 'Ostermontag')

    const thu = addDays(es, 39)
    const bridgeWeekend = 'Brückentag / Wochenende'
    add(thu, 'Christi Himmelfahrt')
    add(addDays(thu, 1), bridgeWeekend)
    add(addDays(thu, 2), bridgeWeekend)
    add(addDays(thu, 3), bridgeWeekend)

    add(addDays(es, 49), 'Pfingstsonntag')
    add(addDays(es, 50), 'Pfingstmontag')
  }

  const out: SwissHolidayCalendarDay[] = []
  for (const [key, label] of map) {
    const [yy, mm, dd] = key.split('-').map((s) => parseInt(s, 10))
    out.push({ date: startOfLocalDay(new Date(yy, mm - 1, dd)), label })
  }
  out.sort((a, b) => a.date.getTime() - b.date.getTime())
  return out
}

/** Marker für @vuepic/vue-datepicker (Feiertagspunkte unter dem Tag) */
export function swissHolidayDatePickerMarkers(minYear: number, maxYear: number): DatePickerMarker[] {
  return swissHolidayCalendarDays(minYear, maxYear).map((h) => ({
    date: h.date,
    type: 'dot',
    color: 'var(--emc-brand-accent, #059669)',
    tooltip: [{ text: h.label }],
  }))
}
