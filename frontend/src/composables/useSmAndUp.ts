import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/** Ab sm (600px) — entspricht Vuetify sm und activity-create-wizard CSS */
export const SM_BREAKPOINT_MIN_PX = 600

const MEDIA_QUERY = `(min-width: ${SM_BREAKPOINT_MIN_PX}px)`

function readSmAndUp(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia(MEDIA_QUERY).matches
}

/**
 * Zuverlässiger als useDisplay().smAndUp / smAndDown bei mobileBreakpoint: 'md'
 * (dort ist smAndDown fälschlich true unter 960px).
 */
export function useSmAndUp(): Ref<boolean> {
  const smAndUp = ref(readSmAndUp())

  onMounted(() => {
    const mq = window.matchMedia(MEDIA_QUERY)
    const onChange = () => {
      smAndUp.value = mq.matches
    }
    onChange()
    mq.addEventListener('change', onChange)
    onBeforeUnmount(() => mq.removeEventListener('change', onChange))
  })

  return smAndUp
}
