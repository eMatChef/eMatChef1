import axios from 'axios'
import { logSessionEvent } from '@/utils/sessionDiagnostics'
import { clearAuthStorage } from '@/utils/authStorage'
import { shouldSkipLoginRedirect, loginRedirectUrl } from '@/api/unauthorizedRedirect'
import { isOnboardingSandboxIncludeActive } from '@/api/onboardingSandboxFlag'

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
    clearAuthStorage()
    if (!shouldSkipLoginRedirect(window.location.pathname)) {
      window.location.assign(loginRedirectUrl(window.location.pathname + window.location.search))
    }
  } finally {
    refreshPromise = null
    if (shouldSkipLoginRedirect(window.location.pathname)) {
      isHandlingSessionExpiry = false
    }
  }
}

/** Nach Login zurücksetzen, damit bei erneutem 401 wieder reagiert wird */
export function resetSessionExpiredHandling() {
  isHandlingSessionExpiry = false
  refreshPromise = null
  lastApiSuccessRefresh = Date.now()
}

/** Mutex: Nur ein Refresh gleichzeitig */
let refreshPromise: Promise<boolean> | null = null

let apiSuccessRefreshCallback: (() => void) | null = null
let lastApiSuccessRefresh = 0
const API_SUCCESS_REFRESH_INTERVAL = 25 * 60 * 1000

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

/** Volle URL für window.location (OAuth), inkl. VITE_API_BASE auf Droplets. */
export function absoluteApiUrl(path: string): string {
  const p = path.startsWith('/') ? path : `/${path}`
  const base = resolveApiBaseURL()
  return base ? `${base}${p}` : p
}

const apiClient = axios.create({
  baseURL: resolveApiBaseURL(),
  withCredentials: true,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

function isPublicApiUrl(url: string): boolean {
  if (url.includes('/api/public/')) {
    return true
  }
  return false
}

function isSessionProbeUrl(url: string): boolean {
  return url.includes('/api/auth/session')
}

function stripAuthorizationHeader(config: { headers?: unknown }): void {
  const headers = config.headers as Record<string, unknown> | undefined
  if (!headers) return
  delete headers.Authorization
  delete headers.authorization
}

/**
 * Authentifizierung nur über HttpOnly-Cookies (BEARER + refresh_token).
 * Kein Authorization-Header aus localStorage.
 */
apiClient.interceptors.request.use((config) => {
  const requestUrl = String(config.url || '')

  if (requestUrl.includes('/token/refresh') || requestUrl.includes('/login_check')) {
    stripAuthorizationHeader(config)
    return config
  }

  if (isPublicApiUrl(requestUrl)) {
    stripAuthorizationHeader(config)
    return config
  }

  stripAuthorizationHeader(config)

  if (isOnboardingSandboxIncludeActive()) {
    const headers = (config.headers ?? {}) as Record<string, unknown>
    if (!headers['X-Onboarding-Tour']) {
      headers['X-Onboarding-Tour'] = '1'
    }
    config.headers = headers as typeof config.headers
    const params = (config.params ?? {}) as Record<string, unknown>
    if (params.include_onboarding_sandbox === undefined) {
      params.include_onboarding_sandbox = '1'
      config.params = params
    }
  }

  return config
})

async function probeSessionValid(): Promise<boolean> {
  try {
    await apiClient.get('/api/auth/session')
    return true
  } catch {
    return false
  }
}

async function performRefreshPost(): Promise<boolean> {
  try {
    logSessionEvent({ type: 'REFRESH_START' })
    await apiClient.post('/api/token/refresh', {})
    logSessionEvent({ type: 'REFRESH_SUCCESS' })
    return true
  } catch (e: unknown) {
    const err = e as { response?: { status?: number; data?: { message?: string } }; message?: string }
    const status = err?.response?.status
    const msg = err?.response?.data?.message || err?.message
    logSessionEvent({ type: 'REFRESH_FAILED', status, message: String(msg) })
    return false
  }
}

/**
 * Einziger Einstieg für Token-Refresh (Mutex gegen single_use-Race).
 * Bei fehlgeschlagenem Refresh: Session-Probe — BEARER kann noch gültig sein.
 */
export async function refreshSessionCookie(): Promise<boolean> {
  if (refreshPromise) {
    logSessionEvent({ type: 'REFRESH_MUTEX_WAIT' })
    return refreshPromise
  }

  logSessionEvent({ type: 'REFRESH_MUTEX_ACQUIRED' })
  refreshPromise = (async () => {
    if (await performRefreshPost()) {
      return true
    }
    const stillValid = await probeSessionValid()
    if (stillValid) {
      logSessionEvent({ type: 'REFRESH_FAILED_SESSION_STILL_VALID' })
    }
    return stillValid
  })().finally(() => {
    refreshPromise = null
    logSessionEvent({ type: 'REFRESH_MUTEX_RELEASED' })
  })

  return refreshPromise
}

async function handleRefreshEndpointFailure(): Promise<void> {
  if (await probeSessionValid()) {
    logSessionEvent({ type: 'REFRESH_FAILED_SESSION_STILL_VALID' })
    return
  }
  await triggerSessionExpired('Refresh-Endpoint fehlgeschlagen')
}

apiClient.interceptors.response.use(
  (response) => {
    const url = String(response.config?.url || '')
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

    if (requestUrl.includes('/token/refresh')) {
      await handleRefreshEndpointFailure()
      return Promise.reject(error)
    }

    if (
      requestUrl.includes('/api/auth/login_check') ||
      requestUrl.includes('/api/auth/register') ||
      requestUrl.includes('/api/auth/verify') ||
      requestUrl.includes('/api/auth/resend-verification') ||
      requestUrl.includes('/api/auth/password-reset/')
    ) {
      return Promise.reject(error)
    }

    if (isPublicApiUrl(requestUrl)) {
      return Promise.reject(error)
    }

    if (isSessionProbeUrl(requestUrl)) {
      return Promise.reject(error)
    }

    if (error?.response?.status === 401 && !originalRequest._retry) {
      const method = originalRequest?.method?.toUpperCase() || '?'
      logSessionEvent({ type: '401_RECEIVED', url: requestUrl, method })

      originalRequest._retry = true

      const refreshed = await refreshSessionCookie()
      if (refreshed) {
        stripAuthorizationHeader(originalRequest)
        return apiClient(originalRequest)
      }

      await triggerSessionExpired('Token-Refresh fehlgeschlagen')
      return Promise.reject(error)
    }

    return Promise.reject(error)
  },
)

export default apiClient
