import axios from 'axios'
import { logSessionEvent } from '@/utils/sessionDiagnostics'
import { isAuthFormPath, loginRedirectUrl } from '@/api/unauthorizedRedirect'

/** Handler für abgelaufene Session (401) – wird in main.ts registriert */
let sessionExpiredHandler: (() => void | Promise<void>) | null = null
let isHandlingSessionExpiry = false

export function setSessionExpiredHandler(handler: () => void | Promise<void>) {
  sessionExpiredHandler = handler
}

async function triggerSessionExpired(reason: string): Promise<void> {
  if (isHandlingSessionExpiry) return
  isHandlingSessionExpiry = true
  logSessionEvent({ type: 'SESSION_EXPIRED_TRIGGERED', reason })
  try {
    if (sessionExpiredHandler) {
      await sessionExpiredHandler()
      return
    }
    localStorage.removeItem('auth_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('user_id')
    localStorage.removeItem('profile_id')
    localStorage.removeItem('session_last_activity_at')
    if (!isAuthFormPath(window.location.pathname)) {
      window.location.assign(loginRedirectUrl(window.location.pathname + window.location.search))
    }
  } finally {
    refreshPromise = null
    if (isAuthFormPath(window.location.pathname)) {
      isHandlingSessionExpiry = false
    }
  }
}

/** Nach Login zurücksetzen, damit bei erneutem 401 wieder reagiert wird */
export function resetSessionExpiredHandling() {
  isHandlingSessionExpiry = false
  refreshPromise = null
  lastApiSuccessRefresh = Date.now() // Kein sofortiger Refresh nach Login – Token ist frisch
}

/** Mutex: Nur ein Refresh gleichzeitig – verhindert parallele Refreshs bei mehreren 401s */
let refreshPromise: Promise<{ token: string; refresh_token: string } | null> | null = null

/** Bei erfolgreichem API-Call: Refresh auslösen (User ist aktiv) – wird in main.ts registriert */
let apiSuccessRefreshCallback: (() => void) | null = null
let lastApiSuccessRefresh = 0
const API_SUCCESS_REFRESH_INTERVAL = 25 * 60 * 1000 // 25 Min

export function setApiSuccessRefreshCallback(cb: () => void) {
  apiSuccessRefreshCallback = cb
}

function resolveApiBaseURL(): string {
  const rel = import.meta.env.VITE_RELATIVE_API
  if (rel === 'true' || rel === '1') {
    return ''
  }
  const base = import.meta.env.VITE_API_BASE
  if (typeof base === 'string' && base.length > 0) {
    return base.replace(/\/$/, '')
  }
  return 'http://localhost:8081'
}

const apiClient = axios.create({
  baseURL: resolveApiBaseURL(),
  withCredentials: true,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }
})

function isPublicApiUrl(url: string): boolean {
  // Öffentliche Endpoints dürfen NICHT mit einem (ggf. abgelaufenen) JWT "mitgeschickt" werden,
  // sonst kann Symfony/JWT-Auth vor PUBLIC_ACCESS abbrechen (401) und QR-Links wirken "tot".
  if (url.includes('/api/public/')) {
    return true
  }
  return false
}

function isSessionProbeUrl(url: string): boolean {
  return url.includes('/api/auth/session')
}

// Request Interceptor - fügt Auth-Token hinzu
apiClient.interceptors.request.use((config) => {
  // Skip Auth-Header für Token Refresh und Login
  if (config.url?.includes('/token/refresh') || config.url?.includes('/login_check')) {
    return config
  }

  const requestUrl = String(config.url || '')
  if (isPublicApiUrl(requestUrl)) {
    return config
  }

  // Session-Probe: nur HttpOnly-Cookies — ein abgelaufener Authorization-Header
  // könnte sonst vor dem Cookie-Extractor ausgewertet werden.
  if (isSessionProbeUrl(requestUrl)) {
    delete (config.headers as Record<string, unknown>).Authorization
    return config
  }

  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response Interceptor - behandelt Auth-Fehler
apiClient.interceptors.response.use(
  (response) => {
    const url = String(response.config?.url || '')
    // Nur triggern wenn Session bereits stabil (lastApiSuccessRefresh > 0).
    // Verhindert sofortigen Refresh beim ersten API-Call nach Reload/Login.
    if (
      apiSuccessRefreshCallback &&
      lastApiSuccessRefresh > 0 &&
      !url.includes('login') &&
      !url.includes('token/refresh') &&
      !isPublicApiUrl(url)
    ) {
      const now = Date.now()
      if (now - lastApiSuccessRefresh > API_SUCCESS_REFRESH_INTERVAL) {
        lastApiSuccessRefresh = now
        apiSuccessRefreshCallback()
      }
    }
    return response
  },
  async (error) => {
    const originalRequest = error.config
    const requestUrl = String(originalRequest?.url || '')

    // Refresh-Request selbst NICHT nochmal versuchen
    if (requestUrl.includes('/token/refresh')) {
      const status = error?.response?.status
      const msg = error?.response?.data?.message || error?.message
      logSessionEvent({ type: 'REFRESH_FAILED', status, message: String(msg) })
      await triggerSessionExpired('Refresh-Endpoint fehlgeschlagen')
      return Promise.reject(error)
    }

    // Bei Auth-Endpunkten nie Refresh versuchen:
    // login/register/verify/password-reset sollen direkte Fehler liefern.
    if (
      requestUrl.includes('/api/auth/login_check') ||
      requestUrl.includes('/api/auth/register') ||
      requestUrl.includes('/api/auth/verify') ||
      requestUrl.includes('/api/auth/resend-verification') ||
      requestUrl.includes('/api/auth/password-reset/')
    ) {
      return Promise.reject(error)
    }

    // Public API: kein Redirect – Fehler an Caller
    if (isPublicApiUrl(requestUrl)) {
      return Promise.reject(error)
    }

    // Session-Probe / Cookie-SSO: kein Token-Refresh, bei 401 sofort Login
    if (isSessionProbeUrl(requestUrl)) {
      if (error?.response?.status === 401) {
        await triggerSessionExpired('Session-Endpoint 401')
      }
      return Promise.reject(error)
    }

    if (error?.response?.status === 401 && !originalRequest._retry) {
      const method = originalRequest?.method?.toUpperCase() || '?'
      logSessionEvent({ type: '401_RECEIVED', url: requestUrl, method })

      originalRequest._retry = true

      const refreshToken = localStorage.getItem('refresh_token')
      if (refreshToken) {
        // Mutex: Nur ein Refresh gleichzeitig – andere 401s warten und nutzen das Ergebnis
        if (!refreshPromise) {
          logSessionEvent({ type: 'REFRESH_MUTEX_ACQUIRED' })
          refreshPromise = (async () => {
            try {
              logSessionEvent({ type: 'REFRESH_START' })
              const { data } = await apiClient.post<{ token: string; refresh_token: string }>(
                '/api/token/refresh',
                { refresh_token: refreshToken }
              )
              logSessionEvent({ type: 'REFRESH_SUCCESS' })
              return data
            } catch (e: any) {
              const status = e?.response?.status
              const msg = e?.response?.data?.message || e?.message
              logSessionEvent({ type: 'REFRESH_FAILED', status, message: String(msg) })
              return null
            } finally {
              refreshPromise = null
              logSessionEvent({ type: 'REFRESH_MUTEX_RELEASED' })
            }
          })()
        } else {
          logSessionEvent({ type: 'REFRESH_MUTEX_WAIT' })
        }

        const data = await refreshPromise
        if (data) {
          localStorage.setItem('auth_token', data.token)
          if (data.refresh_token) {
            localStorage.setItem('refresh_token', data.refresh_token)
          }
          originalRequest.headers.Authorization = `Bearer ${data.token}`
          return apiClient(originalRequest)
        }

        await triggerSessionExpired('Token-Refresh fehlgeschlagen')
        return Promise.reject(error)
      }

      // Kein Refresh Token vorhanden
      logSessionEvent({ type: 'NO_REFRESH_TOKEN' })
      await triggerSessionExpired('Kein Refresh-Token')
    }
    
    return Promise.reject(error)
  }
)

export default apiClient
