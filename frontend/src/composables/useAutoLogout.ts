import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useConfirmStore } from '@/stores/confirm'
import { useToastStore } from '@/stores/toast'
import { getPublicRuntimeConfig } from '@/api/publicRuntimeConfig'
import { getPostLogoutPath } from '@/utils/appLoginUrl'

const LAST_ACTIVITY_KEY = 'session_last_activity_at'
const DEFAULT_ACTIVITY_EVENTS = ['click', 'keydown', 'scroll'] as const
const ALLOWED_ACTIVITY_EVENTS = ['click', 'keydown', 'scroll', 'mousemove', 'wheel', 'touchmove'] as const

function readEnvMs(name: string, fallbackMs: number, minMs: number): number {
  const raw = Number((import.meta.env as Record<string, string | undefined>)[name] || '')
  if (!Number.isFinite(raw) || raw <= 0) return fallbackMs
  return Math.max(minMs, Math.trunc(raw))
}

function readEnvEvents(): string[] {
  const raw = String((import.meta.env as Record<string, string | undefined>).VITE_AUTOLOGOUT_ACTIVITY_EVENTS || '')
    .trim()
    .toLowerCase()
  if (!raw) return [...DEFAULT_ACTIVITY_EVENTS]
  const parsed = raw
    .split(',')
    .map((v) => v.trim())
    .filter((v): v is string => Boolean(v) && (ALLOWED_ACTIVITY_EVENTS as readonly string[]).includes(v))
  return parsed.length > 0 ? parsed : [...DEFAULT_ACTIVITY_EVENTS]
}

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
  const logoutAtMs = ref(0)
  const sessionWarningOpen = ref(false)

  const INACTIVITY_TIMEOUT = readEnvMs('VITE_AUTOLOGOUT_TIMEOUT_MS', 30 * 60 * 1000, 60 * 1000)
  const WARNING_BEFORE_LOGOUT = readEnvMs('VITE_AUTOLOGOUT_WARNING_MS', 3 * 60 * 1000, 15 * 1000)
  const ACTIVITY_THROTTLE = readEnvMs('VITE_AUTOLOGOUT_ACTIVITY_THROTTLE_MS', 5000, 500)
  const PERIODIC_REFRESH_INTERVAL = readEnvMs('VITE_AUTOLOGOUT_REFRESH_INTERVAL_MS', 25 * 60 * 1000, 60 * 1000)
  const ACTIVITY_EVENTS = readEnvEvents()
  const inactivityTimeoutMs = ref(INACTIVITY_TIMEOUT)
  const warningBeforeLogoutMs = ref(WARNING_BEFORE_LOGOUT)
  const activityThrottleMs = ref(ACTIVITY_THROTTLE)
  const periodicRefreshIntervalMs = ref(PERIODIC_REFRESH_INTERVAL)
  const activityEvents = ref<string[]>([...ACTIVITY_EVENTS])

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
    router.push(getPostLogoutPath())
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
    router.push(getPostLogoutPath())
  }

  /**
   * Wenn seit letzter Nutzeraktivität INACTIVITY_TIMEOUT überschritten: abmelden.
   * Wichtig: Vorher wurde bei Tab-Rückkehr blind refresh + Timer-Reset gemacht – damit war
   * „über Nacht offen“ weiter nutzbar.
   */
  function isIdleSessionExpired(): boolean {
    return Date.now() - lastActivity.value >= inactivityTimeoutMs.value
  }

  function resetInactivityTimer() {
    if (!isLoggedIn.value) {
      return
    }

    clearInactivityTimers()

    const warningDelay = Math.max(0, inactivityTimeoutMs.value - warningBeforeLogoutMs.value)
    logoutAtMs.value = Date.now() + inactivityTimeoutMs.value
    warningTimer.value = setTimeout(async () => {
      warningTimer.value = null
      if (!isLoggedIn.value) return

      sessionWarningOpen.value = true
      try {
        const verlängern = await confirm.confirm({
          title: t('errors.sessionExpiringTitle'),
          message: t('errors.sessionExpiringMessage'),
          confirmText: t('errors.extendSession'),
          cancelText: t('layout.userMenu.logout'),
          variant: 'warning',
          persistent: true,
          countdownEndsAt: logoutAtMs.value,
        })

        if (verlängern) {
          const now = Date.now()
          lastActivity.value = now
          persistLastActivity(now)
          resetInactivityTimer()
        } else if (isLoggedIn.value) {
          await doLogout()
        }
      } finally {
        sessionWarningOpen.value = false
      }
    }, warningDelay)

    inactivityTimer.value = setTimeout(async () => {
      inactivityTimer.value = null
      await logoutDueToInactivity()
    }, inactivityTimeoutMs.value)
  }

  function trackActivity() {
    if (!isLoggedIn.value || sessionWarningOpen.value) {
      return
    }

    const now = Date.now()

    if (now - lastActivity.value < activityThrottleMs.value) {
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
    }, periodicRefreshIntervalMs.value)
  }

  function bindActivityListeners() {
    activityEvents.value.forEach((event) => {
      if (event === 'scroll' || event === 'wheel' || event === 'touchmove' || event === 'mousemove') {
        document.addEventListener(event, trackActivity, { passive: true, capture: true })
      } else {
        document.addEventListener(event, trackActivity, { capture: true })
      }
    })
  }

  function unbindActivityListeners() {
    activityEvents.value.forEach((event) => {
      document.removeEventListener(event, trackActivity, { capture: true } as EventListenerOptions)
    })
  }

  async function loadRuntimeAutologoutConfig() {
    // DEV: .env.development bleibt maßgeblich (länger). PROD: Server-Settings.
    if (import.meta.env.DEV) return
    try {
      const cfg = await getPublicRuntimeConfig()
      if (!cfg.autologout) return
      inactivityTimeoutMs.value = Math.max(60 * 1000, cfg.autologout.timeoutMs || inactivityTimeoutMs.value)
      warningBeforeLogoutMs.value = Math.max(15 * 1000, cfg.autologout.warningMs || warningBeforeLogoutMs.value)
      activityThrottleMs.value = Math.max(500, cfg.autologout.activityThrottleMs || activityThrottleMs.value)
      periodicRefreshIntervalMs.value = Math.max(60 * 1000, cfg.autologout.refreshIntervalMs || periodicRefreshIntervalMs.value)
      const csv = String(cfg.autologout.activityEvents || '').trim().toLowerCase()
      if (csv) {
        const parsed = csv
          .split(',')
          .map((v) => v.trim())
          .filter((v): v is string => Boolean(v) && (ALLOWED_ACTIVITY_EVENTS as readonly string[]).includes(v))
        if (parsed.length > 0) {
          unbindActivityListeners()
          activityEvents.value = parsed
          bindActivityListeners()
        }
      }
      if (isLoggedIn.value) {
        clearAllTimers()
        resetInactivityTimer()
        startPeriodicRefresh()
      }
    } catch {
      // Öffentliche Runtime-Config optional; ENV bleibt Fallback.
    }
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
    if (sessionWarningOpen.value) {
      return
    }
    authStore.refreshTokenProactively()
    resetInactivityTimer()
  }

  onMounted(() => {
    // Event Listener für User-Aktivität (per ENV konfigurierbar, z. B. click-only).
    bindActivityListeners()

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
    void loadRuntimeAutologoutConfig()
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

    unbindActivityListeners()
  })

  return {
    resetInactivityTimer
  }
}
