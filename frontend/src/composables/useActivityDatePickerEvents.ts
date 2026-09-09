import { computed, ref, watch, type MaybeRefOrGetter, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDate } from 'vuetify'
import { listDepartmentCalendarPeriods, type DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import { getDepartmentCalendarMarkers } from '@/api/calendarMarkers'
import { toIsoDateKey } from '@/utils/activityDateIso'
import {
  ACTIVITY_DATE_PICKER_MARKER_BADGE_COLORS,
  addDayMarker,
  periodLabelToMarkerKind,
  type ActivityDatePickerDayMarker,
} from '@/utils/activityDatePickerMarkers'
import { isoDateKeysInRange } from '@/utils/calendarPeriodDays'
import { swissHolidayCalendarDays } from '@/utils/swissMovableFeasts'
import { useCalendarPeriodsCacheRevision } from '@/composables/useCalendarPeriodsCache'

/** Feiertage, fcal, Fixe Daten — nur Hinweise (Punkte/Tooltip). Nur department_break sperrt Auswahl. */
export function useActivityDatePickerEvents(
  departmentId: MaybeRefOrGetter<string | null | undefined>,
  options?: {
    showMarkers?: MaybeRefOrGetter<boolean>
    /** department_break-Tage nicht wählbar (Aktivitäten); in Fixe-Daten-Einstellungen false */
    blockClosedDates?: MaybeRefOrGetter<boolean>
  },
) {
  const { t } = useI18n()
  const adapter = useDate()
  const cacheRevision = useCalendarPeriodsCacheRevision()
  const markersByDay = ref<Map<string, ActivityDatePickerDayMarker[]>>(new Map())
  const departmentClosedDateKeys = ref<Set<string>>(new Set())
  const calendarPeriods = ref<DepartmentCalendarPeriod[]>([])

  function markersEnabled(): boolean {
    return toValue(options?.showMarkers) !== false
  }

  function clearMarkers(): void {
    markersByDay.value = new Map()
    departmentClosedDateKeys.value = new Set()
    calendarPeriods.value = []
  }

  async function refreshFixedPeriodMarkers(): Promise<void> {
    if (!markersEnabled()) return
    const deptId = toValue(departmentId)
    if (!deptId) return
    try {
      const y = new Date().getFullYear()
      const rows = await listDepartmentCalendarPeriods(deptId, [y - 1, y, y + 1, y + 2])
      const map = new Map(markersByDay.value)
      const departmentClosed = new Set<string>()

      for (const row of rows) {
        const typeLabel = t(`settings.fixedDates.labels.${row.label}`)
        const tooltip = `${typeLabel}: ${row.name}`
        const kind = periodLabelToMarkerKind(row.label)
        const badgeColor = ACTIVITY_DATE_PICKER_MARKER_BADGE_COLORS[kind]
        for (const key of isoDateKeysInRange(row.start_date, row.end_date)) {
          addDayMarker(map, key, { kind, label: tooltip, badgeColor })
          if (row.label === 'department_break') departmentClosed.add(key)
        }
      }

      markersByDay.value = map
      departmentClosedDateKeys.value = departmentClosed
      calendarPeriods.value = rows
    } catch {
      // Keine Marker — z. B. ohne Berechtigung (sollte für Mitglieder nicht passieren)
    }
  }

  async function refreshSchoolMarkers(): Promise<void> {
    if (!markersEnabled()) return
    const deptId = toValue(departmentId)
    if (!deptId) return
    try {
      const y = new Date().getFullYear()
      const res = await getDepartmentCalendarMarkers(deptId, [y - 1, y, y + 1, y + 2])
      if (res.source !== 'fcal') return

      const map = new Map(markersByDay.value)
      for (const m of res.markers) {
        const key = toIsoDateKey(new Date(m.date))
        addDayMarker(map, key, {
          kind: 'school_holiday',
          label: t('activities.dateRangePicker.schoolHolidayTooltip', { label: m.label }),
          badgeColor: ACTIVITY_DATE_PICKER_MARKER_BADGE_COLORS.school_holiday,
        })
      }
      markersByDay.value = map
    } catch {
      // fcal optional
    }
  }

  function applySwissHolidayMarkers(): void {
    if (!markersEnabled()) {
      clearMarkers()
      return
    }
    const y = new Date().getFullYear()
    const map = new Map<string, ActivityDatePickerDayMarker[]>()
    for (const h of swissHolidayCalendarDays(y - 1, y + 6)) {
      const key = toIsoDateKey(h.date)
      addDayMarker(map, key, {
        kind: 'swiss_holiday',
        label: h.label,
        badgeColor: ACTIVITY_DATE_PICKER_MARKER_BADGE_COLORS.swiss_holiday,
      })
    }
    markersByDay.value = map
    departmentClosedDateKeys.value = new Set()
  }

  async function refreshAll(): Promise<void> {
    applySwissHolidayMarkers()
    await refreshSchoolMarkers()
    await refreshFixedPeriodMarkers()
  }

  watch(
    () => [toValue(departmentId), toValue(options?.showMarkers), cacheRevision.value] as const,
    () => {
      if (!markersEnabled()) {
        clearMarkers()
        return
      }
      void refreshAll()
    },
    { immediate: true },
  )

  const allowedDates = computed(() => {
    if (toValue(options?.blockClosedDates) === false) return () => true
    if (!markersEnabled()) return () => true
    const closed = departmentClosedDateKeys.value
    return (date: unknown) => {
      const js = date instanceof Date ? date : adapter.toJsDate(date as never)
      if (!js || !Number.isFinite(js.getTime())) return false
      return !closed.has(toIsoDateKey(js))
    }
  })

  function markersForIsoKey(isoKey: string | null | undefined): ActivityDatePickerDayMarker[] {
    if (!markersEnabled() || !isoKey) return []
    return markersByDay.value.get(isoKey) ?? []
  }

  return { markersByDay, departmentClosedDateKeys, calendarPeriods, allowedDates, markersForIsoKey }
}
