import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CalendarPeriodLabel, DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import {
  activityRangeQuickPresets,
  activitySingleDayPresets,
  calendarPeriodRangePresets,
  filterValidActivityDatePresets,
  type ActivityDatePresetItem,
} from '@/utils/activityDatePresets'

export function useActivityDatePresets(
  mode: MaybeRefOrGetter<'single' | 'range' | 'fixed-periods'>,
  calendarPeriods: MaybeRefOrGetter<readonly DepartmentCalendarPeriod[]>,
) {
  const { t } = useI18n()

  return computed((): ActivityDatePresetItem[] => {
    const resolvedMode = toValue(mode)
    if (resolvedMode === 'fixed-periods') {
      return filterValidActivityDatePresets(
        calendarPeriodRangePresets(toValue(calendarPeriods), (label: CalendarPeriodLabel) =>
          t(`settings.fixedDates.labels.${label}`),
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
            ...calendarPeriodRangePresets(toValue(calendarPeriods), (label: CalendarPeriodLabel) =>
              t(`settings.fixedDates.labels.${label}`),
            ),
          ]

    return filterValidActivityDatePresets(base)
  })
}
