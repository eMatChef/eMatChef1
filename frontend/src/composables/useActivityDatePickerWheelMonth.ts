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

/**
 * Monatsnavigation: Mausrad (vertikal) und Touch-Wischen horizontal.
 * Links wischen → vorheriger Monat, rechts wischen → nächster Monat.
 */
export function useActivityDatePickerWheelMonth(options: {
  month: Ref<number>
  year: Ref<number>
  onAfterChange?: () => void
}) {
  const touchStartX = ref<number | null>(null)
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
    touchStartX.value = e.touches[0].clientX
    touchStartY.value = e.touches[0].clientY
  }

  function onTouchEnd(e: TouchEvent) {
    const startX = touchStartX.value
    const startY = touchStartY.value
    touchStartX.value = null
    touchStartY.value = null
    if (startX == null || startY == null) return
    const endX = e.changedTouches[0]?.clientX
    const endY = e.changedTouches[0]?.clientY
    if (endX == null || endY == null) return
    const dx = endX - startX
    const dy = endY - startY
    if (Math.abs(dx) < SWIPE_THRESHOLD_PX) return
    // Nur deutlich horizontale Wischgesten (vertikal = Menü-Scroll)
    if (Math.abs(dx) <= Math.abs(dy)) return
    applyMonthDelta(dx < 0 ? 1 : -1)
  }

  return { onWheel, onTouchStart, onTouchEnd }
}
