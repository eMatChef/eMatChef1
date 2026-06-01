import { ref, type Ref } from 'vue'

export function shiftMonthYear(
  month: number,
  year: number,
  delta: number,
): { month: number; year: number } {
  const d = new Date(year, month + delta, 1)
  return { month: d.getMonth(), year: d.getFullYear() }
}

const SWIPE_THRESHOLD_PX = 48

/** Mausrad und Touch-Wischen auf dem Kalender: Monat vor/zurück. */
export function useActivityDatePickerWheelMonth(options: {
  month: Ref<number>
  year: Ref<number>
  onAfterChange?: () => void
}) {
  const touchStartY = ref<number | null>(null)

  function applyMonthDelta(delta: number) {
    const next = shiftMonthYear(options.month.value, options.year.value, delta)
    options.month.value = next.month
    options.year.value = next.year
    options.onAfterChange?.()
  }

  function onWheel(e: WheelEvent) {
    e.preventDefault()
    applyMonthDelta(e.deltaY > 0 ? 1 : -1)
  }

  function onTouchStart(e: TouchEvent) {
    if (e.touches.length !== 1) return
    touchStartY.value = e.touches[0].clientY
  }

  function onTouchEnd(e: TouchEvent) {
    const startY = touchStartY.value
    touchStartY.value = null
    if (startY == null) return
    const endY = e.changedTouches[0]?.clientY
    if (endY == null) return
    const dy = endY - startY
    if (Math.abs(dy) < SWIPE_THRESHOLD_PX) return
    // Nach unten wischen → nächster Monat (wie Mausrad nach unten)
    applyMonthDelta(dy > 0 ? 1 : -1)
  }

  return { onWheel, onTouchStart, onTouchEnd }
}
