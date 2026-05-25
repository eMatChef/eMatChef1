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
import { shouldProbeUserSession, shouldSkipLoginRedirect, loginRedirectUrl } from './api/unauthorizedRedirect'
import { i18n, setLocale } from './i18n'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)

const authStore = useAuthStore()

watch(
  () => authStore.profile?.language,
  (language) => {
    if (language) {
      setLocale(language)
    }
  },
  { immediate: true },
)

setApiSuccessRefreshCallback(() => {
  useAuthStore().refreshTokenProactively()
})

setSessionExpiredHandler(async () => {
  useToastStore().warning(i18n.global.t('errors.sessionExpired'), 5000)
  await useAuthStore().logout()
  const path = window.location.pathname
  if (shouldSkipLoginRedirect(path)) return
  const fullPath = router.currentRoute.value?.fullPath || path + window.location.search
  const target = loginRedirectUrl(fullPath)
  try {
    await router.replace(target)
  } catch {
    window.location.assign(target)
  }
})

async function bootstrapUserSession(): Promise<void> {
  if (!shouldProbeUserSession()) {
    return
  }

  const token = localStorage.getItem('auth_token')

  if (token) {
    try {
      await authStore.loadUserSession()
    } catch (error) {
      console.error('Failed to load session on init:', error)
      localStorage.removeItem('auth_token')
      localStorage.removeItem('refresh_token')
      localStorage.removeItem('user_id')
      localStorage.removeItem('profile_id')
      localStorage.removeItem('session_last_activity_at')
    }
    return
  }

  try {
    await authStore.loadUserSessionFromCookie()
  } catch {
    // Nicht eingeloggt ist auf öffentlichen Seiten normal.
  }
}

async function initApp() {
  await bootstrapUserSession()

  app.use(router)
  app.use(i18n)

  app.mount('#app')

  await router.isReady()
  syncDocumentHead(router.currentRoute.value)
}

void initApp()
