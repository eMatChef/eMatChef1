import { createApp } from 'vue'
import { watch } from 'vue'
import './style.css'
import 'vuetify/styles'
import 'vuetify/lib/components/VField/VField.css'
import 'vuetify/lib/components/VInput/VInput.css'
import '@mdi/font/css/materialdesignicons.css'
import './styles/views/activities/pack-workflow-modals.css'
import App from './App.vue'
import router from './router'
import { syncDocumentHead } from './composables/usePageHead'
import { createPinia } from 'pinia'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import { setSessionExpiredHandler, setApiSuccessRefreshCallback } from './api/apiClient'
import { shouldProbeUserSession, shouldSkipLoginRedirect, loginRedirectUrl } from './api/unauthorizedRedirect'
import { applyCrossSubdomainLogoutSync } from './utils/authCrossOrigin'
import { purgeLegacyAuthSecrets } from './utils/authStorage'
import { i18n, setLocale } from './i18n'
import vuetify from './plugins/vuetify'

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

async function redirectToLoginAfterSessionEnd(): Promise<void> {
  const path = window.location.pathname
  if (shouldSkipLoginRedirect(path)) return
  const fullPath = router.currentRoute.value?.fullPath || path + window.location.search
  const target = loginRedirectUrl(fullPath)
  try {
    await router.replace(target)
  } catch {
    window.location.assign(target)
  }
}

setSessionExpiredHandler(async () => {
  useToastStore().warning(i18n.global.t('errors.sessionExpired'), 5000)
  await useAuthStore().logout()
  await redirectToLoginAfterSessionEnd()
})

function syncCrossSubdomainLogoutToStore(): boolean {
  if (!applyCrossSubdomainLogoutSync()) return false
  authStore.clearAuthState()
  return true
}

function setupCrossSubdomainLogoutListener(): void {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return
    if (!syncCrossSubdomainLogoutToStore()) return
    void redirectToLoginAfterSessionEnd()
  })
}

async function bootstrapUserSession(): Promise<void> {
  if (!shouldProbeUserSession()) {
    return
  }

  purgeLegacyAuthSecrets()
  syncCrossSubdomainLogoutToStore()

  try {
    await authStore.loadUserSessionFromCookie()
  } catch {
    authStore.clearAuthState()
  }
}

async function initApp() {
  await bootstrapUserSession()

  app.use(router)
  app.use(i18n)
  app.use(vuetify)

  setupCrossSubdomainLogoutListener()

  app.mount('#app')

  await router.isReady()
  syncDocumentHead(router.currentRoute.value)
}

void initApp()
