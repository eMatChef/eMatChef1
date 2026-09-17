import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import { localDateToIsoDateString } from '@/utils/activityDateIso'

export function startOfLocalDay(d: Date): Date {
  const x = new Date(d.getTime())
  x.setHours(0, 0, 0, 0)
  return x
}

/** Kalendertag + Uhrzeit (Zeit aus zweitem Datum) */
export function combineDayAndTime(day: Date, time: Date): Date {
  const x = new Date(day.getTime())
  x.setHours(time.getHours(), time.getMinutes(), 0, 0)
  return snapDateToQuarterHour(x)
}

function pad2(n: number): string {
  return String(n).padStart(2, '0')
}

/** Lokale Wanduhr ohne Zeitzone, z. B. `2027-08-21T15:30:00`. */
export function localDateTimeToIso(d: Date): string {
  return `${localDateToIsoDateString(d)}T${pad2(d.getHours())}:${pad2(d.getMinutes())}:00`
}

/** ISO-Datum+Uhrzeit als lokale Wanduhr lesen (Offset/Z ignorieren). */
export function parseLocalDateTimeIso(iso: string | null | undefined): Date | null {
  if (!iso) return null
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(iso.trim())
  if (!m) {
    const fallback = new Date(iso)
    return Number.isNaN(fallback.getTime()) ? null : fallback
  }
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]), 0, 0)
}
