import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CalendarPeriodLabel, DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import { useAuthStore } from '@/stores/auth'
import {
  activityRangeQuickPresets,
  activitySingleDayPresets,
  CALENDAR_PERIOD_LABELS_QUICK_SELECT_GROSSANLASS,
  CALENDAR_PERIOD_LABELS_QUICK_SELECT_MATERIAL,
  calendarPeriodRangePresets,
  filterValidActivityDatePresets,
  type ActivityDatePresetItem,
} from '@/utils/activityDatePresets'

export function useActivityDatePresets(
  mode: MaybeRefOrGetter<'single' | 'range' | 'fixed-periods'>,
  calendarPeriods: MaybeRefOrGetter<readonly DepartmentCalendarPeriod[]>,
  departmentId?: MaybeRefOrGetter<string | null | undefined>,
) {
  const { t } = useI18n()
  const authStore = useAuthStore()

  return computed((): ActivityDatePresetItem[] => {
    const resolvedMode = toValue(mode)
    if (resolvedMode === 'fixed-periods') {
      const deptId = toValue(departmentId)
      const isGrossanlass = deptId ? authStore.isDepartmentGrossanlass(deptId) : false
      const quickLabels = isGrossanlass
        ? CALENDAR_PERIOD_LABELS_QUICK_SELECT_GROSSANLASS
        : CALENDAR_PERIOD_LABELS_QUICK_SELECT_MATERIAL
      return filterValidActivityDatePresets(
        calendarPeriodRangePresets(
          toValue(calendarPeriods),
          (label: CalendarPeriodLabel) => t(`settings.fixedDates.labels.${label}`),
          quickLabels,
        ),
      )
    }

    const base =
      resolvedMode === 'single'
        ? activitySingleDayPresets({
            nextSaturday: t('activities.datePresets.nextSaturday'),
            secondSaturday: t('activities.datePresets.secondSaturday'),
          })
        : [
            ...activityRangeQuickPresets({
              nextSaturday: t('activities.datePresets.nextSaturday'),
              secondSaturday: t('activities.datePresets.secondSaturday'),
            }),
            ...calendarPeriodRangePresets(
              toValue(calendarPeriods),
              (label: CalendarPeriodLabel) => t(`settings.fixedDates.labels.${label}`),
            ),
          ]

    return filterValidActivityDatePresets(base)
  })
}
