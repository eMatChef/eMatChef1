import { isoDateStringToLocalDate, toIsoDateKey } from '@/utils/activityDateIso'

/** Alle ISO-Tageskeys (inkl.) zwischen zwei YYYY-MM-DD. */
export function isoDateKeysInRange(startIso: string, endIso: string): string[] {
  const start = isoDateStringToLocalDate(startIso)
  const end = isoDateStringToLocalDate(endIso)
  if (!start || !end || end < start) return []

  const keys: string[] = []
  const cur = new Date(start.getTime())
  while (cur <= end) {
    keys.push(toIsoDateKey(cur))
    cur.setDate(cur.getDate() + 1)
  }
  return keys
}
