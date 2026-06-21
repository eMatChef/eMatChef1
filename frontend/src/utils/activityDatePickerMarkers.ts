import type { CalendarPeriodLabel } from '@/api/calendarPeriods'

export type ActivityDatePickerMarkerKind =
  | 'swiss_holiday'
  | 'school_holiday'
  | 'school_vacation'
  | 'department_break'
  | 'camp_week'
  | 'other'
  | 'grossanlass'

export interface ActivityDatePickerDayMarker {
  kind: ActivityDatePickerMarkerKind
  label: string
  /** Vuetify-Theme-Farbe für VBadge dot (s. Date Events) */
  badgeColor: string
}

/** Theme-Farben wie Vuetify Date Events (red / blue / yellow …) */
export const ACTIVITY_DATE_PICKER_MARKER_BADGE_COLORS: Record<ActivityDatePickerMarkerKind, string> = {
  swiss_holiday: 'success',
  school_holiday: 'info',
  school_vacation: 'deep-purple',
  department_break: 'error',
  camp_week: 'warning',
  other: 'grey-darken-1',
  grossanlass: 'primary',
}

export function periodLabelToMarkerKind(label: CalendarPeriodLabel): ActivityDatePickerMarkerKind {
  return label
}

export function addDayMarker(
  map: Map<string, ActivityDatePickerDayMarker[]>,
  isoKey: string,
  marker: ActivityDatePickerDayMarker,
): void {
  const list = map.get(isoKey) ?? []
  if (list.some((m) => m.kind === marker.kind && m.label === marker.label)) return
  list.push(marker)
  map.set(isoKey, list)
}

export function markersForDay(
  map: ReadonlyMap<string, ActivityDatePickerDayMarker[]> | null | undefined,
  isoKey: string | null | undefined,
): ActivityDatePickerDayMarker[] {
  if (!isoKey || !map) return []
  return map.get(isoKey) ?? []
}

/** Akzentfarbe für Schnellauswahl-Einträge (gleiche Zuordnung wie Kalender-Marker). */
export function activityDatePresetAccentColor(preset: {
  periodLabel?: CalendarPeriodLabel
}): string {
  if (preset.periodLabel) {
    return ACTIVITY_DATE_PICKER_MARKER_BADGE_COLORS[preset.periodLabel] ?? 'primary'
  }
  return 'primary'
}
