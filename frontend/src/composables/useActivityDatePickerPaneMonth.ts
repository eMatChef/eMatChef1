import { ref, watch, type MaybeRefOrGetter, toValue } from 'vue'
import { shiftMonthYear, useActivityDatePickerWheelMonth } from './useActivityDatePickerWheelMonth'

/** Ein Kalender-Pane: gesteuertes Monat/Jahr + Mausrad-Navigation. */
export function useActivityDatePickerPaneMonth(options: {
  menuOpen?: MaybeRefOrGetter<boolean | undefined>
  anchorDate?: MaybeRefOrGetter<Date | null | undefined>
}) {
  const now = new Date()
  const month = ref(now.getMonth())
  const year = ref(now.getFullYear())

  function anchorFromDate(base: Date) {
    month.value = base.getMonth()
    year.value = base.getFullYear()
  }

  function shiftMonth(delta: number) {
    const next = shiftMonthYear(month.value, year.value, delta)
    month.value = next.month
    year.value = next.year
  }

  watch(
    () => toValue(options.menuOpen),
    (open) => {
      if (!open) return
      const anchor = toValue(options.anchorDate)
      const base =
        anchor && Number.isFinite(anchor.getTime()) ? anchor : new Date()
      anchorFromDate(base)
    },
  )

  const { onWheel, onTouchStart, onTouchEnd } = useActivityDatePickerWheelMonth({ month, year })

  return {
    month,
    year,
    shiftMonth,
    onWheel,
    onTouchStart,
    onTouchEnd,
    onMonthFromPicker: (m: number) => {
      month.value = m
    },
    onYearFromPicker: (y: number) => {
      year.value = y
    },
  }
}
