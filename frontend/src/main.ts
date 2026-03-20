import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { createPinia } from 'pinia'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import { setSessionExpiredHandler, setApiSuccessRefreshCallback } from './api/apiClient'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Bei erfolgreichem API-Call: Token proaktiv erneuern (User ist aktiv)
setApiSuccessRefreshCallback(() => {
  useAuthStore().refreshTokenProactively()
})

// 401-Handler: Toast + Auth-Store + Redirect (statt nur localStorage leeren)
setSessionExpiredHandler(async () => {
  useToastStore().warning('Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.', 5000)
  await useAuthStore().logout()
  if (window.location.pathname !== '/') {
    router.push('/')
  }
})

// Session laden VOR dem Mounten (wichtig für Router-Guards!)
async function initApp() {
  const authStore = useAuthStore()
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
    }
  }
  
  // App mounten nach Session-Laden
  app.mount('#app')
}

initApp()
