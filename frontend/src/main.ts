import { createApp } from 'vue'
import { watch } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { syncDocumentHead } from './composables/usePageHead'
import { createPinia } from 'pinia'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import { setSessionExpiredHandler, setApiSuccessRefreshCallback } from './api/apiClient'
import { i18n, setLocale } from './i18n'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(i18n)

const authStore = useAuthStore()
watch(
  () => authStore.profile?.language,
  (language) => {
    if (language) {
      setLocale(language)
    }
  },
  { immediate: true }
)

// Bei erfolgreichem API-Call: Token proaktiv erneuern (User ist aktiv)
setApiSuccessRefreshCallback(() => {
  useAuthStore().refreshTokenProactively()
})

// 401-Handler: Toast + Auth-Store + Redirect (statt nur localStorage leeren)
setSessionExpiredHandler(async () => {
  useToastStore().warning(i18n.global.t('errors.sessionExpired'), 5000)
  await useAuthStore().logout()
  const requiresAuth = router.currentRoute.value.matched.some((r) => r.meta.requiresAuth)
  if (requiresAuth && window.location.pathname !== '/login') {
    await router.push('/login')
  }
})

// Session laden VOR dem Mounten (wichtig für Router-Guards!)
async function initApp() {
  const token = localStorage.getItem('auth_token')
  
  if (token) {
    try {
      // Session laden und warten bis fertig
      await authStore.loadUserSession()
    } catch (error) {
      console.error('Failed to load session on init:', error)
      // Token ist ungültig, entfernen
      localStorage.removeItem('auth_token')
      localStorage.removeItem('refresh_token')
      localStorage.removeItem('user_id')
      localStorage.removeItem('profile_id')
      localStorage.removeItem('session_last_activity_at')
    }
  } else {
    // Cookie-SSO Bootstrap: auf jeder Origin frühzeitig Session aus HttpOnly-Cookies laden
    // (Avatar/Name im Header ohne zusätzlichen Klick/Navigation verfügbar).
    try {
      await authStore.loadUserSessionFromCookie()
    } catch {
      // Öffentlich nicht eingeloggt ist ein normaler Zustand.
    }
  }
  
  // App mounten nach Session-Laden
  app.mount('#app')

  await router.isReady()
  syncDocumentHead(router.currentRoute.value)
}

initApp()
