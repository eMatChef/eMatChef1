import type { ActivityDefaults } from '@/api/departmentSettings'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

/**
 * Uhrzeit auf festes Viertelstunden-Raster 00 / 15 / 30 / 45 (lokal, über Tagesminuten).
 * Rundet die gesamte Tageszeit — nicht nur die Minuten innerhalb der Stunde (sonst wäre 12:52 → 12:45 statt 13:00).
 */
export function snapDateToQuarterHour(d: Date): Date {
  const x = new Date(d.getTime())
  const totalMin = x.getHours() * 60 + x.getMinutes()
  const snapped = Math.round(totalMin / 15) * 15
  const h = Math.floor(snapped / 60)
  const m = snapped % 60
  x.setHours(h, m, 0, 0)
  return x
}

function parseHHMM(s: string): { h: number; m: number } {
  const [a, b] = s.split(':').map((x) => parseInt(x, 10))
  return { h: a || 0, m: Number.isFinite(b) ? b : 0 }
}

/** Abteilungs-Fixzeit (HH:mm) auf 00/15/30/45 normalisieren — Vorlage für Nutzung/Material */
export function normalizeDepartmentTimeHHMM(timeHHMM: string): string {
  const { h, m } = parseHHMM(timeHHMM.trim())
  const d = new Date(2000, 0, 1, h, m, 0, 0)
  const s = snapDateToQuarterHour(d)
  const hh = String(s.getHours()).padStart(2, '0')
  const mm = String(s.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}

/** Beliebiger Kalendertag (lokal) mit Uhrzeit aus "HH:mm" (Fixzeiten-Vorlage, Viertelstunden-Raster) */
export function dayAtTime(day: Date, timeHHMM: string): Date {
  const norm = normalizeDepartmentTimeHHMM(timeHHMM)
  const { h, m } = parseHHMM(norm)
  const d = new Date(day.getFullYear(), day.getMonth(), day.getDate())
  d.setHours(h, m, 0, 0)
  return d
}

/** Heute (lokal) mit Uhrzeit aus "HH:mm" */
export function todayAtTime(timeHHMM: string): Date {
  return dayAtTime(new Date(), timeHHMM)
}

/**
 * Nächster Samstag ab heute (lokal, 00:00). Ist heute Samstag, wird heute zurückgegeben.
 */
export function nextSaturdayFromToday(): Date {
  const now = new Date()
  const dow = now.getDay()
  const sat = 6
  const daysUntil = (sat - dow + 7) % 7
  const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + daysUntil)
  d.setHours(0, 0, 0, 0)
  return d
}

/**
 * Standard-Nutzungsfenster aus Abteilung auf dem nächsten Samstag: defaultTimeStart / defaultTimeEnd.
 * Liegt das Ende vor dem Start am selben Kalendertag, wird der Endtag +1 genommen.
 */
export function defaultUsageWindowFromDepartmentDefaults(defaults: ActivityDefaults): { usageStart: Date; usageEnd: Date } {
  const day = nextSaturdayFromToday()
  const start = dayAtTime(day, defaults.defaultTimeStart)
  let end = dayAtTime(day, defaults.defaultTimeEnd)
  if (end.getTime() <= start.getTime()) {
    end = new Date(end.getTime())
    end.setDate(end.getDate() + 1)
    end = snapDateToQuarterHour(end)
  }
  return { usageStart: start, usageEnd: end }
}

/**
 * Material-Zeitraum aus Aktivitätsnutzung (wie in v4.01 / DepartmentSetting):
 * - activity / event / external: Vorlauf/Nachlauf in Minuten
 * - camp: Vorlauf/Nachlauf in Kalendertagen
 */
export function computeMaterialPlanningFromUsage(
  usageStart: Date,
  usageEnd: Date,
  defaults: ActivityDefaults,
  type: ActivityCreateType,
): { planningStart: Date; planningEnd: Date } {
  if (type === 'camp') {
    const ps = new Date(usageStart.getTime())
    ps.setDate(ps.getDate() - defaults.campMaterialLeadDays)
    const pe = new Date(usageEnd.getTime())
    pe.setDate(pe.getDate() + defaults.campMaterialLagDays)
    return { planningStart: snapDateToQuarterHour(ps), planningEnd: snapDateToQuarterHour(pe) }
  }
  const ps = new Date(usageStart.getTime() - defaults.materialLeadMinutes * 60 * 1000)
  const pe = new Date(usageEnd.getTime() + defaults.materialLagMinutes * 60 * 1000)
  return { planningStart: snapDateToQuarterHour(ps), planningEnd: snapDateToQuarterHour(pe) }
}
