import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useConfirmStore } from '@/stores/confirm'
import { useToastStore } from '@/stores/toast'

const LAST_ACTIVITY_KEY = 'session_last_activity_at'

function readLastActivityMs(): number {
  const raw = localStorage.getItem(LAST_ACTIVITY_KEY)
  if (raw) {
    const n = parseInt(raw, 10)
    if (!Number.isNaN(n)) return n
  }
  return Date.now()
}

export function useAutoLogout() {
  const { t } = useI18n()
  const router = useRouter()
  const authStore = useAuthStore()
  const confirm = useConfirm()
  const confirmStore = useConfirmStore()
  const toast = useToastStore()
  const inactivityTimer = ref<NodeJS.Timeout | null>(null)
  const warningTimer = ref<NodeJS.Timeout | null>(null)
  /** Letzte echte Nutzeraktivität (Klick/Tastatur/Scroll), persistiert für Reload & Tab-Rückkehr */
  const lastActivity = ref(readLastActivityMs())

  const INACTIVITY_TIMEOUT = 60 * 60 * 1000 // 60 Minuten
  const WARNING_BEFORE_LOGOUT = 5 * 60 * 1000 // 5 Minuten Vorwarnung
  const ACTIVITY_THROTTLE = 5000 // Nur alle 5 Sekunden prüfen (Performance)
  const PERIODIC_REFRESH_INTERVAL = 25 * 60 * 1000 // 25 Min – zeitbasiert, unabhängig von Klicks

  const isLoggedIn = computed(() => authStore.isLoggedIn)
  let periodicRefreshIntervalId: ReturnType<typeof setInterval> | null = null

  function persistLastActivity(ts: number) {
    localStorage.setItem(LAST_ACTIVITY_KEY, String(ts))
  }

  function clearInactivityTimers() {
    if (inactivityTimer.value) {
      clearTimeout(inactivityTimer.value)
      inactivityTimer.value = null
    }
    if (warningTimer.value) {
      clearTimeout(warningTimer.value)
      warningTimer.value = null
    }
  }

  function clearAllTimers() {
    clearInactivityTimers()
    if (periodicRefreshIntervalId) {
      clearInterval(periodicRefreshIntervalId)
      periodicRefreshIntervalId = null
    }
  }

  async function doLogout() {
    clearAllTimers()
    confirmStore.cancel() // Schließt ggf. offene Session-Warnung
    await authStore.logout()
    router.push('/login')
  }

  /** Abmeldung wegen Inaktivität (Toast + Redirect) */
  async function logoutDueToInactivity() {
    if (!isLoggedIn.value) return
    clearAllTimers()
    confirmStore.cancel()
    toast.warning(
      'Deine Sitzung ist abgelaufen (Inaktivität). Bitte melde dich erneut an.',
      5000
    )
    await authStore.logout()
    router.push('/login')
  }

  /**
   * Wenn seit letzter Nutzeraktivität INACTIVITY_TIMEOUT überschritten: abmelden.
   * Wichtig: Vorher wurde bei Tab-Rückkehr blind refresh + Timer-Reset gemacht – damit war
   * „über Nacht offen“ weiter nutzbar.
   */
  function isIdleSessionExpired(): boolean {
    return Date.now() - lastActivity.value >= INACTIVITY_TIMEOUT
  }

  function resetInactivityTimer() {
    if (!isLoggedIn.value) {
      return
    }

    clearInactivityTimers()

    // Vorwarnung 5 Min vor Ablauf
    warningTimer.value = setTimeout(async () => {
      warningTimer.value = null
      if (!isLoggedIn.value) return

      const verlängern = await confirm.confirm({
        title: t('errors.sessionExpiringTitle'),
        message: t('errors.sessionExpiringMessage'),
        confirmText: t('errors.extendSession'),
        cancelText: t('layout.userMenu.logout'),
        variant: 'warning',
      })

      if (verlängern) {
        resetInactivityTimer()
      } else {
        await doLogout()
      }
    }, INACTIVITY_TIMEOUT - WARNING_BEFORE_LOGOUT)

    // Logout nach 60 Min Inaktivität
    inactivityTimer.value = setTimeout(async () => {
      inactivityTimer.value = null
      await logoutDueToInactivity()
    }, INACTIVITY_TIMEOUT)
  }

  function trackActivity() {
    if (!isLoggedIn.value) {
      return
    }

    const now = Date.now()

    if (now - lastActivity.value < ACTIVITY_THROTTLE) {
      return
    }

    lastActivity.value = now
    persistLastActivity(now)
    resetInactivityTimer()
  }

  function startPeriodicRefresh() {
    if (periodicRefreshIntervalId) return
    periodicRefreshIntervalId = setInterval(() => {
      if (!isLoggedIn.value) {
        clearAllTimers()
        return
      }
      if (isIdleSessionExpired()) {
        void logoutDueToInactivity()
        return
      }
      authStore.refreshTokenProactively()
    }, PERIODIC_REFRESH_INTERVAL)
  }

  function onVisibilityChange() {
    if (document.visibilityState !== 'visible' || !isLoggedIn.value) {
      return
    }
    lastActivity.value = readLastActivityMs()
    if (isIdleSessionExpired()) {
      void logoutDueToInactivity()
      return
    }
    authStore.refreshTokenProactively()
    resetInactivityTimer()
  }

  onMounted(() => {
    // Event Listener für User-Aktivität – Klicks, Tastatur, Scroll (auch bei Leaflet-Karte etc.)
    ;['click', 'keydown'].forEach(event => {
      document.addEventListener(event, trackActivity, { capture: true })
    })
    document.addEventListener('scroll', trackActivity, { passive: true, capture: true })

    document.addEventListener('visibilitychange', onVisibilityChange)

    lastActivity.value = readLastActivityMs()
    if (isLoggedIn.value && isIdleSessionExpired()) {
      void logoutDueToInactivity()
      return
    }

    resetInactivityTimer()
    if (isLoggedIn.value) {
      startPeriodicRefresh()
    }
  })

  watch(isLoggedIn, (newValue, oldValue) => {
    if (newValue && !oldValue) {
      // Aus localStorage (Login setzt session_last_activity_at) – nicht Date.now(), sonst
      // würde jeder Reload die Inaktivitätsfrist zurücksetzen.
      lastActivity.value = readLastActivityMs()
      resetInactivityTimer()
      startPeriodicRefresh()
    } else if (!newValue && oldValue) {
      clearAllTimers()
    }
  })

  onUnmounted(() => {
    clearAllTimers()
    document.removeEventListener('visibilitychange', onVisibilityChange)

    ;['click', 'keydown'].forEach(event => {
      document.removeEventListener(event, trackActivity, { capture: true } as EventListenerOptions)
    })
    document.removeEventListener('scroll', trackActivity, { passive: true, capture: true } as EventListenerOptions)
  })

  return {
    resetInactivityTimer
  }
}
