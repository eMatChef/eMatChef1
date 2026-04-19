/** 15-Minuten-Raster für Aktivitätszeiten (Viertelstunden). */

export const ACTIVITY_TIME_STEP_MINUTES = 15

export function snapMinutesToStep(totalMinutes: number, step: number): number {
  const rounded = Math.round(totalMinutes / step) * step
  return ((rounded % 1440) + 1440) % 1440
}

export function snapTimeHHMM(value: string): string {
  if (!value || typeof value !== 'string') return value
  const parts = value.trim().split(':')
  if (parts.length < 2) return value
  const h = parseInt(parts[0], 10)
  const m = parseInt(parts[1], 10)
  if (Number.isNaN(h) || Number.isNaN(m)) return value
  const snapped = snapMinutesToStep(h * 60 + m, ACTIVITY_TIME_STEP_MINUTES)
  const nh = Math.floor(snapped / 60)
  const nm = snapped % 60
  return `${String(nh).padStart(2, '0')}:${String(nm).padStart(2, '0')}`
}

export function snapDatetimeLocalToStep(value: string): string {
  if (!value || !value.includes('T')) return value
  const [d, t] = value.split('T')
  const timePart = (t || '').slice(0, 5)
  if (!/^\d{2}:\d{2}$/.test(timePart)) return value
  return `${d}T${snapTimeHHMM(timePart)}`
}
