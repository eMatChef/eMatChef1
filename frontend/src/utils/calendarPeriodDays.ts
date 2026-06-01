import { toIsoDateKey } from '@/utils/activityDateIso'

/** Alle ISO-Tageskeys (inkl.) zwischen zwei YYYY-MM-DD. */
export function isoDateKeysInRange(startIso: string, endIso: string): string[] {
  const start = parseIsoDate(startIso)
  const end = parseIsoDate(endIso)
  if (!start || !end || end < start) return []

  const keys: string[] = []
  const cur = new Date(start.getTime())
  while (cur <= end) {
    keys.push(toIsoDateKey(cur))
    cur.setDate(cur.getDate() + 1)
  }
  return keys
}

function parseIsoDate(iso: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso.trim())
  if (!m) return null
  const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
  d.setHours(0, 0, 0, 0)
  if (d.getFullYear() !== Number(m[1]) || d.getMonth() !== Number(m[2]) - 1 || d.getDate() !== Number(m[3])) {
    return null
  }
  return d
}
