/**
 * App-Instanz (Login) vs. öffentliche Hauptdomain – siehe VITE_APP_ORIGIN / VITE_MAIN_SITE_ORIGIN.
 */
export function getAppLoginPath(): string {
  return '/login'
}

export function getAppLoginTarget(): string {
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  if (appOrigin && typeof window !== 'undefined') {
    try {
      const u = new URL(appOrigin)
      return `${u.origin}/login`
    } catch {
      /* ignore */
    }
  }
  return '/login'
}

export function getAppEntryTarget(): string {
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  if (appOrigin && typeof window !== 'undefined') {
    try {
      const u = new URL(appOrigin)
      return `${u.origin}/`
    } catch {
      /* ignore */
    }
  }
  return '/'
}

export function getMainSiteOrigin(): string {
  return (import.meta.env.VITE_MAIN_SITE_ORIGIN || '').trim().replace(/\/$/, '')
}

const qrPublicHost = (import.meta.env.VITE_QR_PUBLIC_HOST || '').trim().toLowerCase()

/** Öffentliche QR-/Info-Hostnames (z. B. qr.localhost, qr.ematchef.ch). */
export function isQrPublicHost(): boolean {
  if (!qrPublicHost || typeof window === 'undefined') return false
  return window.location.hostname.toLowerCase() === qrPublicHost
}

export function isAppOrigin(): boolean {
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  if (!appOrigin || typeof window === 'undefined') return false
  try {
    const u = new URL(appOrigin)
    return window.location.origin === u.origin
  } catch {
    return false
  }
}

/**
 * Nach manuellem Logout: App-Instanz (z. B. app.localhost) immer Login, keine Marketing-Startseite.
 * Haupt-/Marketing-Origin → Startseite „/“.
 */
export function getPostLogoutPath(): string {
  if (isAppOrigin()) {
    return '/login'
  }
  return '/'
}
