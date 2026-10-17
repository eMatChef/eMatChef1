function isSameDay(date1: string, date2: string): boolean {
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

function pad2(n: number): string {
  return String(n).padStart(2, '0')
}

function formatDayMonthYear(d: Date): string {
  return `${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${String(d.getFullYear()).slice(-2)}`
}

function formatRange(start: Date, end: Date | null): string {
  if (!end || start.toDateString() === end.toDateString()) return formatDayMonthYear(start)
  const d1 = pad2(start.getDate())
  const m1 = pad2(start.getMonth() + 1)
  const y1 = String(start.getFullYear()).slice(-2)
  const d2 = pad2(end.getDate())
  const m2 = pad2(end.getMonth() + 1)
  const y2 = String(end.getFullYear()).slice(-2)
  const sameMonth = start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()
  if (sameMonth && y1 === y2) return `${d1}.–${d2}.${m1}.${y1}`
  if (y1 === y2) return `${d1}.${m1}.–${d2}.${m2}.${y2}`
  return `${d1}.${m1}.${y1}–${d2}.${m2}.${y2}`
}

/** z. B. 14.03.26 oder 14.–18.03.26 */
export function formatPeriodCompact(startStr?: string | null, endStr?: string | null): string {
  if (!startStr) return ''
  const start = new Date(startStr)
  if (Number.isNaN(start.getTime())) return ''
  if (!endStr || isSameDay(startStr, endStr)) return formatDayMonthYear(start)
  const end = new Date(endStr)
  if (Number.isNaN(end.getTime())) return formatDayMonthYear(start)
  return formatRange(start, end)
}

/** Datum und Uhrzeit ohne Zeitzonenumrechnung (die API liefert Wandzeit mit «+00:00»-Suffix). */
function parseWallClock(iso: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(iso.trim())
  if (!m) return null
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4] ?? 0), Number(m[5] ?? 0), Number(m[6] ?? 0))
}

/**
 * Wie {@link formatPeriodCompact}, aber für Anlass-Zeiträume der Grossanlässe: Beginn und Ende sind gespeicherte
 * Wandzeit (Ende = letzter Tag, 23:59:59, inklusiv). Eine Umrechnung in die Browser-Zeitzone würde das Ende in
 * Zeitzonen östlich von UTC auf den Folgetag schieben.
 */
export function formatEventPeriodCompact(startStr?: string | null, endStr?: string | null): string {
  const start = startStr ? parseWallClock(startStr) : null
  if (!start) return ''
  return formatRange(start, endStr ? parseWallClock(endStr) : null)
}
