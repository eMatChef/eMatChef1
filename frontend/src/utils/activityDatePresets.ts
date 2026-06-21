import type { CalendarPeriodLabel, DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import {
  nextSaturdayFromToday,
  secondSaturdayFromToday,
} from '@/utils/activityPlanningFromDefaults'
import { startOfLocalDay, startOfToday } from '@/utils/swissMovableFeasts'

export type ActivityDatePresetValue = Date | [Date, Date]

export interface ActivityDatePresetItem {
  label: string
  value: ActivityDatePresetValue
  /** Fixe-Daten-Art — Farbe in Schnellauswahl (nur camp_week / other) */
  periodLabel?: CalendarPeriodLabel
}

function presetEndDate(value: ActivityDatePresetValue): Date {
  return startOfLocalDay(value instanceof Date ? value : value[1])
}

/** Preset ausblenden, wenn der Zeitraum ganz in der Vergangenheit liegt. */
export function isActivityDatePresetStillValid(preset: ActivityDatePresetItem): boolean {
  return presetEndDate(preset.value).getTime() >= startOfToday().getTime()
}

export function filterValidActivityDatePresets(
  presets: ActivityDatePresetItem[],
): ActivityDatePresetItem[] {
  return presets.filter(isActivityDatePresetStillValid)
}

function parseIsoDateLocal(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number)
  return startOfLocalDay(new Date(y, m - 1, d))
}

/** Schnellauswahl: Material-Department */
export const CALENDAR_PERIOD_LABELS_QUICK_SELECT_MATERIAL = ['camp_week', 'other'] as const

/** Schnellauswahl: Grossanlass-Department */
export const CALENDAR_PERIOD_LABELS_QUICK_SELECT_GROSSANLASS = ['grossanlass', 'other'] as const

/** Fixe Daten (Lagerwoche, Sonstiges, Grossanlass) — nur wenn noch nicht vorbei. */
export function calendarPeriodRangePresets(
  periods: readonly DepartmentCalendarPeriod[],
  labelForType: (label: CalendarPeriodLabel) => string,
  quickSelectLabels: readonly CalendarPeriodLabel[] = CALENDAR_PERIOD_LABELS_QUICK_SELECT_MATERIAL,
): ActivityDatePresetItem[] {
  const today = startOfToday()
  const items: ActivityDatePresetItem[] = []
  const allowed = new Set<string>(quickSelectLabels)

  for (const row of periods) {
    if (!allowed.has(row.label)) continue
    const end = parseIsoDateLocal(row.end_date)
    if (end.getTime() < today.getTime()) continue
    const start = parseIsoDateLocal(row.start_date)
    items.push({
      label: `${labelForType(row.label)}: ${row.name}`,
      value: [start, end],
      periodLabel: row.label,
    })
  }

  return items.sort((a, b) => {
    const sa = a.value instanceof Date ? a.value : a.value[0]
    const sb = b.value instanceof Date ? b.value : b.value[0]
    return sa.getTime() - sb.getTime()
  })
}

function saturdayQuickPresets(labels: {
  nextSaturday: string
  secondSaturday: string
}): ActivityDatePresetItem[] {
  const next = startOfLocalDay(nextSaturdayFromToday())
  const second = startOfLocalDay(secondSaturdayFromToday())
  return filterValidActivityDatePresets([
    { label: labels.nextSaturday, value: next },
    { label: labels.secondSaturday, value: second },
  ])
}

/** Typ „Aktivität“: nächster + übernächster Samstag */
export function activitySingleDayPresets(labels: {
  nextSaturday: string
  secondSaturday: string
}): ActivityDatePresetItem[] {
  return saturdayQuickPresets(labels)
}

/** Lager/Event: nächster + übernächster Samstag — Lager/Ferien über Fixe Daten pflegen. */
export function activityRangeQuickPresets(labels: {
  nextSaturday: string
  secondSaturday: string
}): ActivityDatePresetItem[] {
  return saturdayQuickPresets(labels).map((p) => ({
    ...p,
    value: p.value instanceof Date ? [p.value, p.value] : p.value,
  }))
}
