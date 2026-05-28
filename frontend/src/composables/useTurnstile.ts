import { computed, nextTick, onUnmounted, ref } from 'vue'

const TURNSTILE_SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js'

function loadTurnstileScript(): Promise<void> {
  if (window.turnstile) {
    return Promise.resolve()
  }
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${TURNSTILE_SCRIPT_SRC}"]`)
    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true })
      existing.addEventListener('error', () => reject(new Error('Turnstile')), { once: true })
      return
    }
    const s = document.createElement('script')
    s.src = TURNSTILE_SCRIPT_SRC
    s.async = true
    s.defer = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('Turnstile script failed'))
    document.head.appendChild(s)
  })
}

/** Cloudflare Turnstile (Registrierung, Join-/Support-Antraege). */
export function useTurnstile() {
  const siteKey = computed(() => {
    const skip =
      import.meta.env.VITE_TURNSTILE_SKIP === 'true' || import.meta.env.VITE_TURNSTILE_SKIP === '1'
    if (skip) return ''
    return (import.meta.env.VITE_TURNSTILE_SITE_KEY || '').trim()
  })

  const isRequired = computed(() => siteKey.value !== '')

  const containerRef = ref<HTMLElement | null>(null)
  const widgetId = ref<string | null>(null)

  function cleanup(): void {
    if (widgetId.value && window.turnstile?.remove) {
      try {
        window.turnstile.remove(widgetId.value)
      } catch {
        // ignore
      }
    }
    widgetId.value = null
    if (containerRef.value) {
      containerRef.value.innerHTML = ''
    }
  }

  function reset(): void {
    if (widgetId.value && window.turnstile?.reset) {
      window.turnstile.reset(widgetId.value)
    }
  }

  async function init(): Promise<void> {
    if (!siteKey.value) return
    await nextTick()
    if (!containerRef.value) return
    cleanup()
    try {
      await loadTurnstileScript()
    } catch (e) {
      console.error(e)
      return
    }
    await nextTick()
    if (!containerRef.value || !window.turnstile) return
    widgetId.value = window.turnstile.render(containerRef.value, {
      sitekey: siteKey.value,
      theme: 'light',
    })
  }

  function getToken(): string | undefined {
    if (!siteKey.value) return undefined
    const wid = widgetId.value
    if (!wid || !window.turnstile) return undefined
    return window.turnstile.getResponse(wid) || undefined
  }

  onUnmounted(() => cleanup())

  return {
    siteKey,
    isRequired,
    containerRef,
    init,
    cleanup,
    reset,
    getToken,
  }
}
