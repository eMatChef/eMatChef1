import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/** Ab sm (600px) — entspricht Vuetify sm und activity-create-wizard CSS */
export const SM_BREAKPOINT_MIN_PX = 600

/** Zwei Monatskalender nebeneinander (je ~328px) plus Rand. */
export const DUAL_DATE_CALENDAR_MIN_PX = 720

function useMatchMediaMinWidth(minPx: number): Ref<boolean> {
  const query = `(min-width: ${minPx}px)`
  const matches = ref(typeof window !== 'undefined' && window.matchMedia(query).matches)

  onMounted(() => {
    const mq = window.matchMedia(query)
    const onChange = () => {
      matches.value = mq.matches
    }
    onChange()
    mq.addEventListener('change', onChange)
    onBeforeUnmount(() => mq.removeEventListener('change', onChange))
  })

  return matches
}

/**
 * Zuverlässiger als useDisplay().smAndUp / smAndDown bei mobileBreakpoint: 'md'
 * (dort ist smAndDown fälschlich true unter 960px).
 */
export function useSmAndUp(): Ref<boolean> {
  return useMatchMediaMinWidth(SM_BREAKPOINT_MIN_PX)
}

/** Tablet/Desktop: genug Breite für zwei Monatskalender im Zeitraum-Menü. */
export function useDualDateCalendarLayout(): Ref<boolean> {
  return useMatchMediaMinWidth(DUAL_DATE_CALENDAR_MIN_PX)
}
