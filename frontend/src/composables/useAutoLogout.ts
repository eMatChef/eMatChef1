import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useConfirmStore } from '@/stores/confirm'

export function useAutoLogout() {
  const router = useRouter()
  const authStore = useAuthStore()
  const confirm = useConfirm()
  const confirmStore = useConfirmStore()
  const inactivityTimer = ref<NodeJS.Timeout | null>(null)
  const warningTimer = ref<NodeJS.Timeout | null>(null)
  const lastActivity = ref(Date.now())
  
  const INACTIVITY_TIMEOUT = 60 * 60 * 1000 // 60 Minuten
  const WARNING_BEFORE_LOGOUT = 5 * 60 * 1000 // 5 Minuten Vorwarnung
  const ACTIVITY_THROTTLE = 5000 // Nur alle 5 Sekunden prüfen (Performance)
  const PERIODIC_REFRESH_INTERVAL = 25 * 60 * 1000 // 25 Min – zeitbasiert, unabhängig von Klicks
  
  const isLoggedIn = computed(() => authStore.isLoggedIn)
  let periodicRefreshIntervalId: ReturnType<typeof setInterval> | null = null
  
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
        title: 'Session läuft bald ab',
        message: 'Deine Sitzung läuft in wenigen Minuten ab. Möchtest du angemeldet bleiben?',
        confirmText: 'Verlängern',
        cancelText: 'Abmelden',
        variant: 'warning'
      })
      
      if (verlängern) {
        resetInactivityTimer()
      } else {
        await doLogout()
      }
    }, INACTIVITY_TIMEOUT - WARNING_BEFORE_LOGOUT)
    
    // Logout nach 30 Min
    inactivityTimer.value = setTimeout(async () => {
      inactivityTimer.value = null
      await doLogout()
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
    resetInactivityTimer()
  }
  
  function startPeriodicRefresh() {
    if (periodicRefreshIntervalId) return
    periodicRefreshIntervalId = setInterval(() => {
      if (!isLoggedIn.value) {
        clearAllTimers()
        return
      }
      authStore.refreshTokenProactively()
    }, PERIODIC_REFRESH_INTERVAL)
  }
  
  function onVisibilityChange() {
    if (document.visibilityState === 'visible' && isLoggedIn.value) {
      authStore.refreshTokenProactively()
      resetInactivityTimer()
    }
  }

  onMounted(() => {
    // Event Listener für User-Aktivität – Klicks, Tastatur, Scroll (auch bei Leaflet-Karte etc.)
    ;['click', 'keydown'].forEach(event => {
      document.addEventListener(event, trackActivity, { capture: true })
    })
    document.addEventListener('scroll', trackActivity, { passive: true, capture: true })
    
    document.addEventListener('visibilitychange', onVisibilityChange)
    
    resetInactivityTimer()
    if (isLoggedIn.value) {
      startPeriodicRefresh()
    }
  })
  
  watch(isLoggedIn, (newValue, oldValue) => {
    if (newValue && !oldValue) {
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
