/** Viertelstunden-Raster für alle Aktivitäts-Zeitfelder (00 / 15 / 30 / 45). */
export const ACTIVITY_QUARTER_MINUTES = [0, 15, 30, 45] as const

export function isActivityQuarterMinute(minute: number): boolean {
  return (ACTIVITY_QUARTER_MINUTES as readonly number[]).includes(minute)
}

export function formatActivityTimeHHmm(d: Date): string {
  const hh = String(Math.max(0, Math.min(23, d.getHours()))).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}
