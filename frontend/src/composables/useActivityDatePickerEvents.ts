import { computed, ref, watch, type MaybeRefOrGetter, toValue } from 'vue'
import { getDepartmentCalendarMarkers } from '@/api/calendarMarkers'
import { toIsoDateKey } from '@/utils/activityDateIso'
import { swissHolidayCalendarDays } from '@/utils/swissMovableFeasts'

/** Feiertage + fcal-Schulferien als VDatePicker-`events` (Punkte unter dem Tag). */
export function useActivityDatePickerEvents(departmentId: MaybeRefOrGetter<string | null | undefined>) {
  const schoolHolidayKeys = ref<Set<string>>(new Set())

  async function refreshSchoolMarkers(): Promise<void> {
    const deptId = toValue(departmentId)
    if (!deptId) {
      schoolHolidayKeys.value = new Set()
      return
    }
    try {
      const y = new Date().getFullYear()
      const res = await getDepartmentCalendarMarkers(deptId, [y - 1, y, y + 1, y + 2])
      if (res.source !== 'fcal') {
        schoolHolidayKeys.value = new Set()
        return
      }
      schoolHolidayKeys.value = new Set(res.markers.map((m) => toIsoDateKey(new Date(m.date))))
    } catch {
      schoolHolidayKeys.value = new Set()
    }
  }

  watch(() => toValue(departmentId), refreshSchoolMarkers, { immediate: true })

  const holidayEventKeys = computed(() => {
    const y = new Date().getFullYear()
    const keys = new Set<string>()
    for (const h of swissHolidayCalendarDays(y - 1, y + 6)) {
      keys.add(toIsoDateKey(h.date))
    }
    for (const k of schoolHolidayKeys.value) keys.add(k)
    return keys
  })

  const datePickerEvents = computed((): Record<string, true> => {
    const map: Record<string, true> = {}
    for (const k of holidayEventKeys.value) map[k] = true
    return map
  })

  return { datePickerEvents }
}
