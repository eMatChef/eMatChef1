import { computed, onMounted, onUnmounted, ref } from 'vue'

export type DevicesUiMode = 'handheld' | 'desktop'

const DESKTOP_MIN_WIDTH = 900

/**
 * Handheld (TC700H) vs. Desktop Lager (PC + PowerScan).
 * Optional ?mode=handheld|desktop überschreibt.
 */
export function useDevicesUiMode() {
  const queryOverride = ref<DevicesUiMode | null>(null)
  const viewportWide = ref(
    typeof window !== 'undefined' ? window.innerWidth >= DESKTOP_MIN_WIDTH : false,
  )

  function readQueryOverride() {
    if (typeof window === 'undefined') return
    const q = new URLSearchParams(window.location.search).get('mode')?.toLowerCase()
    if (q === 'handheld' || q === 'desktop') {
      queryOverride.value = q
    } else {
      queryOverride.value = null
    }
  }

  function onResize() {
    viewportWide.value = window.innerWidth >= DESKTOP_MIN_WIDTH
  }

  onMounted(() => {
    readQueryOverride()
    window.addEventListener('resize', onResize)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', onResize)
  })

  const mode = computed<DevicesUiMode>(() => {
    if (queryOverride.value) return queryOverride.value
    return viewportWide.value ? 'desktop' : 'handheld'
  })

  const isHandheld = computed(() => mode.value === 'handheld')
  const isDesktop = computed(() => mode.value === 'desktop')

  return { mode, isHandheld, isDesktop }
}
