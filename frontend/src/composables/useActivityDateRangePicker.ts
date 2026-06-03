import { computed, onBeforeUnmount, ref, watch, type MaybeRefOrGetter, type Ref, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  commitActivityDateRange,
  rangeContainsDepartmentClosedDate,
} from '@/utils/activityDatePickerModel'
import { startOfLocalDay } from '@/utils/swissMovableFeasts'

/** Kurz sichtbar lassen, bevor das Menü nach Zeitraum-Auswahl schliesst */
export const ACTIVITY_RANGE_CLOSE_DELAY_MS = 500

export function useActivityDateRangePicker(options: {
  pickerRange: Ref<Date[] | null>
  menuOpen: Ref<boolean>
  onCommit: (range: [Date, Date]) => void
  departmentClosedDateKeys?: MaybeRefOrGetter<ReadonlySet<string>>
}) {
  const { t } = useI18n()
  const toast = useToast()
  const hoverDate = ref<Date | null>(null)
  let closeTimer: ReturnType<typeof setTimeout> | null = null

  function isRangeBlockedByDepartmentClosed(start: Date, end: Date): boolean {
    return rangeContainsDepartmentClosedDate(
      start,
      end,
      toValue(options.departmentClosedDateKeys) ?? new Set(),
    )
  }

  /** Für ActivityDatePickerDay — inkl. Hover-Vorschau zwischen Start und Maus */
  const displayRange = computed((): Date[] | null => {
    const picked = options.pickerRange.value
    if (!picked?.length) return null
    if (picked.length >= 2) {
      const [a, b] = commitActivityDateRange(picked)
      if (isRangeBlockedByDepartmentClosed(a, b)) return [a]
      return picked
    }
    if (picked.length === 1 && hoverDate.value) {
      const a = picked[0]
      const b = hoverDate.value
      const [start, end] = a.getTime() <= b.getTime() ? [a, b] : [b, a]
      if (isRangeBlockedByDepartmentClosed(start, end)) return picked
      return [start, end]
    }
    return picked
  })

  const rangeAnchorCount = computed(() => options.pickerRange.value?.length ?? 0)

  function clearCloseTimer() {
    if (closeTimer) {
      clearTimeout(closeTimer)
      closeTimer = null
    }
  }

  function onDayHover(date: Date) {
    if (options.pickerRange.value?.length !== 1) return
    const start = options.pickerRange.value[0]
    if (isRangeBlockedByDepartmentClosed(start, date)) return
    hoverDate.value = date
  }

  function onRangeUpdate(value: Date | Date[] | null) {
    const prevLen = options.pickerRange.value?.length ?? 0

    if (!value || !Array.isArray(value)) {
      options.pickerRange.value = null
      hoverDate.value = null
      return
    }

    if (value.length >= 2) {
      const committed = commitActivityDateRange(value)
      if (isRangeBlockedByDepartmentClosed(committed[0], committed[1])) {
        options.pickerRange.value = [startOfLocalDay(value[0])]
        hoverDate.value = null
        toast.warning(t('activities.dateRangePicker.rangeBlockedByDepartmentBreak'))
        return
      }
      options.pickerRange.value = value
      hoverDate.value = null
      // Menü erst schliessen, wenn der Zeitraum im laufenden Pick abgeschlossen wird (1 → 2).
      if (prevLen === 1) {
        clearCloseTimer()
        closeTimer = setTimeout(() => {
          options.onCommit(committed)
          options.menuOpen.value = false
          closeTimer = null
        }, ACTIVITY_RANGE_CLOSE_DELAY_MS)
      }
      return
    }

    options.pickerRange.value = value
    if (value.length < 2) hoverDate.value = null
  }

  watch(
    () => options.menuOpen.value,
    (open) => {
      if (!open) {
        hoverDate.value = null
        clearCloseTimer()
      }
    },
  )

  onBeforeUnmount(clearCloseTimer)

  return {
    displayRange,
    rangeAnchorCount,
    onDayHover,
    onRangeUpdate,
  }
}
