import type { Ref } from 'vue'

export function shiftMonthYear(
  month: number,
  year: number,
  delta: number,
): { month: number; year: number } {
  const d = new Date(year, month + delta, 1)
  return { month: d.getMonth(), year: d.getFullYear() }
}

/** Mausrad auf dem Kalender: Monat vor/zurück (deltaY). */
export function useActivityDatePickerWheelMonth(options: {
  month: Ref<number>
  year: Ref<number>
  onAfterChange?: () => void
}) {
  function onWheel(e: WheelEvent) {
    e.preventDefault()
    const delta = e.deltaY > 0 ? 1 : -1
    const next = shiftMonthYear(options.month.value, options.year.value, delta)
    options.month.value = next.month
    options.year.value = next.year
    options.onAfterChange?.()
  }

  return { onWheel }
}
