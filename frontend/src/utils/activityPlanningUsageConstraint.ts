import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import { startOfLocalDay } from '@/utils/activityDateTimeParts'

/** Liegt der Zeitpunkt in der geschlossenen Nutzungszeit [usageStart, usageEnd]? */
export function isInstantInsideClosedUsage(t: Date, usageStart: Date, usageEnd: Date): boolean {
  const x = t.getTime()
  return x >= usageStart.getTime() && x <= usageEnd.getTime()
}

/**
 * Nächstgelegene Viertelstunde am selben Kalendertag wie `anchor`, die nicht in [usageStart, usageEnd] liegt.
 * `null`, wenn der ganze Tag von der Nutzung überdeckt wird (kein erlaubter Slot).
 */
export function nearestAllowedQuarterOnDayOutsideUsage(
  anchor: Date,
  usageStart: Date,
  usageEnd: Date,
): Date | null {
  const day = startOfLocalDay(anchor)
  const endOfDayMs = day.getTime() + 24 * 60 * 60 * 1000 - 1
  const candidates: Date[] = []
  for (let t = day.getTime(); t <= endOfDayMs; t += 15 * 60 * 1000) {
    const d = snapDateToQuarterHour(new Date(t))
    if (!isInstantInsideClosedUsage(d, usageStart, usageEnd)) {
      candidates.push(d)
    }
  }
  if (candidates.length === 0) return null
  let best = candidates[0]
  let bestDist = Math.abs(best.getTime() - anchor.getTime())
  for (const c of candidates) {
    const dist = Math.abs(c.getTime() - anchor.getTime())
    if (dist < bestDist) {
      best = c
      bestDist = dist
    }
  }
  return best
}

/**
 * Abhol- und Rückgabezeitpunkt dürfen nicht innerhalb der geschlossenen Nutzungszeit [usageStart, usageEnd] liegen.
 */
export function getPlanningUsageViolation(
  planningStart: Date,
  planningEnd: Date,
  usageStart: Date,
  usageEnd: Date,
): { pickup: boolean; return: boolean } {
  return {
    pickup: isInstantInsideClosedUsage(planningStart, usageStart, usageEnd),
    return: isInstantInsideClosedUsage(planningEnd, usageStart, usageEnd),
  }
}

export function planningUsageViolationMessage(v: { pickup: boolean; return: boolean }): string | null {
  if (v.pickup && v.return) {
    return 'Abhol- und Rückgabezeit dürfen nicht in der Nutzungszeit liegen.'
  }
  if (v.pickup) return 'Abholzeit darf nicht in der Nutzungszeit liegen.'
  if (v.return) return 'Rückgabezeit darf nicht in der Nutzungszeit liegen.'
  return null
}
