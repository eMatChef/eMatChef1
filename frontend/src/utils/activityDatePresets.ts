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

/** Fixe Daten (Lagerwoche, Schulferien, Sonstiges, …) — nur wenn noch nicht vorbei; kein Mat-Büro geschlossen. */
export function calendarPeriodRangePresets(
  periods: readonly DepartmentCalendarPeriod[],
  labelForType: (label: CalendarPeriodLabel) => string,
): ActivityDatePresetItem[] {
  const today = startOfToday()
  const items: ActivityDatePresetItem[] = []

  for (const row of periods) {
    if (row.label === 'department_break') continue
    const end = parseIsoDateLocal(row.end_date)
    if (end.getTime() < today.getTime()) continue
    const start = parseIsoDateLocal(row.start_date)
    items.push({
      label: `${labelForType(row.label)}: ${row.name}`,
      value: [start, end],
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
