import { startOfLocalDay } from '@/utils/swissMovableFeasts'
import { toIsoDateKey } from '@/utils/activityDateIso'

/** VDatePicker-Range: Ende als Tagesende, damit isWithinRange den Bereich färbt. */
export function normalizeRangeForVDatePicker(dates: Date[] | null | undefined): Date[] {
  if (!dates?.length) return []
  const sorted = [...dates].sort((a, b) => a.getTime() - b.getTime())
  const start = startOfLocalDay(sorted[0])
  if (sorted.length === 1) return [start]
  const end = startOfLocalDay(sorted[sorted.length - 1])
  const endEod = new Date(end)
  endEod.setHours(23, 59, 59, 999)
  return [start, endEod]
}

/** Gespeichertes Modell: beide Grenzen auf 00:00 (lokal). */
export function commitActivityDateRange(dates: Date[]): [Date, Date] {
  const sorted = [...dates].sort((a, b) => a.getTime() - b.getTime())
  return [startOfLocalDay(sorted[0]), startOfLocalDay(sorted[sorted.length - 1])]
}

export function normalizeSingleForVDatePicker(d: Date | null | undefined): Date | null {
  if (!d || !Number.isFinite(d.getTime())) return null
  return startOfLocalDay(d)
}

/** Enthält der Zeitraum (inkl.) mindestens einen Tag «Mat-Büro geschlossen» (department_break)? */
export function rangeContainsDepartmentClosedDate(
  start: Date,
  end: Date,
  departmentClosedKeys: ReadonlySet<string>,
): boolean {
  if (departmentClosedKeys.size === 0) return false
  let from = startOfLocalDay(start)
  let to = startOfLocalDay(end)
  if (from.getTime() > to.getTime()) {
    const tmp = from
    from = to
    to = tmp
  }
  const cur = new Date(from.getTime())
  while (cur.getTime() <= to.getTime()) {
    if (departmentClosedKeys.has(toIsoDateKey(cur))) return true
    cur.setDate(cur.getDate() + 1)
  }
  return false
}
