import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/** Vuetify-Plan: md = 960px (siehe plugins/vuetify.ts) */
export const MD_BREAKPOINT_MIN_PX = 960

const MEDIA_QUERY = `(min-width: ${MD_BREAKPOINT_MIN_PX}px)`

function readMdAndUp(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia(MEDIA_QUERY).matches
}

/**
 * Zuverlässiger als useDisplay().mdAndUp in DevTools / eingebetteten WebViews.
 */
export function useMdAndUp(): Ref<boolean> {
  const mdAndUp = ref(readMdAndUp())

  onMounted(() => {
    const mq = window.matchMedia(MEDIA_QUERY)
    const onChange = () => {
      mdAndUp.value = mq.matches
    }
    onChange()
    mq.addEventListener('change', onChange)
    onBeforeUnmount(() => mq.removeEventListener('change', onChange))
  })

  return mdAndUp
}
