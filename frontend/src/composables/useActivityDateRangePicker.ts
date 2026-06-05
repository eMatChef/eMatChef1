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
  blockClosedDates?: MaybeRefOrGetter<boolean>
}) {
  const { t } = useI18n()
  const toast = useToast()
  const hoverDate = ref<Date | null>(null)
  let closeTimer: ReturnType<typeof setTimeout> | null = null

  function isRangeBlockedByDepartmentClosed(start: Date, end: Date): boolean {
    if (toValue(options.blockClosedDates) === false) return false
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

  const rangeAnchorDate = computed((): Date | null => {
    const picked = options.pickerRange.value
    return picked?.length === 1 ? startOfLocalDay(picked[0]) : null
  })

  function clearCloseTimer() {
    if (closeTimer) {
      clearTimeout(closeTimer)
      closeTimer = null
    }
  }

  function sameCalendarDay(a: Date, b: Date): boolean {
    return startOfLocalDay(a).getTime() === startOfLocalDay(b).getTime()
  }

  function scheduleCommit(committed: [Date, Date], immediate = false) {
    options.pickerRange.value = [committed[0], committed[1]]
    hoverDate.value = null
    clearCloseTimer()
    const delay = immediate ? 0 : ACTIVITY_RANGE_CLOSE_DELAY_MS
    closeTimer = setTimeout(() => {
      options.onCommit(committed)
      options.menuOpen.value = false
      closeTimer = null
    }, delay)
  }

  /** Zweiter Klick auf denselben Tag → Eintags-Zeitraum (Von = Bis). */
  function tryCommitSingleDayAnchor(anchor: Date): boolean {
    const day = startOfLocalDay(anchor)
    if (isRangeBlockedByDepartmentClosed(day, day)) {
      options.pickerRange.value = [day]
      hoverDate.value = null
      toast.warning(t('activities.dateRangePicker.rangeBlockedByDepartmentBreak'))
      return true
    }
    scheduleCommit([day, day], true)
    return true
  }

  function onDayConfirmSameDay(date: Date) {
    const picked = options.pickerRange.value
    if (picked?.length !== 1) return
    const anchor = startOfLocalDay(picked[0])
    if (!sameCalendarDay(startOfLocalDay(date), anchor)) return
    tryCommitSingleDayAnchor(anchor)
  }

  function onDayHover(date: Date) {
    if (options.pickerRange.value?.length !== 1) return
    const start = options.pickerRange.value[0]
    if (isRangeBlockedByDepartmentClosed(start, date)) return
    hoverDate.value = date
  }

  function onRangeUpdate(value: Date | Date[] | null) {
    const prev = options.pickerRange.value
    const prevLen = prev?.length ?? 0
    const prevAnchor = prevLen >= 1 ? startOfLocalDay(prev![0]) : null

    if (!value || !Array.isArray(value)) {
      if (prevAnchor && prevLen === 1 && tryCommitSingleDayAnchor(prevAnchor)) return
      options.pickerRange.value = null
      hoverDate.value = null
      return
    }

    const days = value.map(startOfLocalDay)

    if (days.length === 1 && prevAnchor && prevLen === 1 && sameCalendarDay(days[0], prevAnchor)) {
      tryCommitSingleDayAnchor(prevAnchor)
      return
    }

    if (days.length >= 2) {
      const committed = commitActivityDateRange(value)
      if (isRangeBlockedByDepartmentClosed(committed[0], committed[1])) {
        options.pickerRange.value = [committed[0]]
        hoverDate.value = null
        toast.warning(t('activities.dateRangePicker.rangeBlockedByDepartmentBreak'))
        return
      }
      const singleDay = sameCalendarDay(committed[0], committed[1])
      if (prevLen === 1 || singleDay) {
        scheduleCommit(committed)
        return
      }
      options.pickerRange.value = value
      hoverDate.value = null
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
    rangeAnchorDate,
    onDayHover,
    onDayConfirmSameDay,
    onRangeUpdate,
  }
}
