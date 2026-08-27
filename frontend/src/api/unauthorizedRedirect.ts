import { isAppOrigin } from '@/utils/appLoginUrl'
import { isDevicesHost } from '@/utils/devicesHost'
import { sanitizeLoginRedirectPath } from '@/utils/appHomeRedirect'

/** Pfade, auf denen 401 keinen Login-Redirect auslösen (Formular zeigt Fehler selbst). */
export function isAuthFormPath(pathname: string): boolean {
  return (
    pathname === '/login' ||
    pathname.startsWith('/register') ||
    pathname.startsWith('/verify') ||
    pathname.includes('password-reset')
  )
}

/** Infoscreen-Kiosk: nur Display-Cookie, kein User-JWT / keine Session-Probe. */
export function isDisplayKioskPath(pathname: string): boolean {
  const path = (pathname || '').replace(/\/$/, '') || '/'
  if (path === '/display') return true
  return /^\/display\/[^/]+/.test(path)
}

/** User-Session (/api/auth/session) nur laden, wenn sinnvoll (nicht auf Infoscreen-Kiosk). */
export function shouldProbeUserSession(pathname?: string): boolean {
  const path =
    pathname ??
    (typeof window !== 'undefined' ? window.location.pathname : '')
  if (isDisplayKioskPath(path)) return false
  // Marketing auf Hauptdomain: kein globaler Session-Probe in main.ts (optional Chip in PublicSiteLayout)
  if (!isAppOrigin() && !isDevicesHost() && isPublicMarketingPath(path)) {
    return false
  }
  return true
}

/** Öffentliche QR-Infos ohne Login (z. B. /i/m/…/b/…, /i/a/…, /i/w/…, /i/c/…, /display/…). */
export function isPublicAnonymousPath(pathname: string): boolean {
  if (pathname === '/open-from-qr') return true
  if (isDisplayKioskPath(pathname)) return true
  const parts = pathname.split('/').filter(Boolean)
  if (parts[0] !== 'i') return false
  if (parts[1] === 'b' && parts[2]) return true
  if (parts[1] === 'm' && parts[2] && parts[3] === 'b' && parts[4]) return true
  if (parts[1] === 'm' && parts[2] && parts.length === 3) return true
  if (parts[1] === 'a' && parts[2]) return true
  if (parts[1] === 'w' && parts[2]) return true
  if (parts[1] === 'c' && parts[2]) return true
  return false
}

/** Marketing-Startseite & öffentliche Inhalte auf der Hauptdomain (ematchef.ch). */
export function isPublicMarketingPath(pathname: string): boolean {
  const path = (pathname || '').replace(/\/$/, '') || '/'
  if (path === '/') return true
  const prefixes = ['/blog', '/faq', '/tos', '/impressum', '/datenschutz'] as const
  return prefixes.some((p) => path === p || path.startsWith(`${p}/`))
}

/** Kein Login-Redirect bei Session-Ablauf (Auth-Formulare + öffentliche QR-Seiten). */
export function shouldSkipLoginRedirect(pathname: string): boolean {
  if (isAuthFormPath(pathname) || isPublicAnonymousPath(pathname)) {
    return true
  }
  // Hauptdomain: nach Logout auf Landing/Marketing nicht nach /login?redirect=/ schicken
  if (!isAppOrigin() && !isDevicesHost() && isPublicMarketingPath(pathname)) {
    return true
  }
  return false
}

export function loginRedirectUrl(currentFullPath?: string): string {
  const path = (currentFullPath || '').trim()
  if (!path || path === '/' || path === '/login' || path.startsWith('/login?')) {
    return '/login'
  }
  // Tour-URL nicht als Redirect merken → Department-Dashboard (oder kein redirect)
  const sanitized = sanitizeLoginRedirectPath(path)
  if (!sanitized || sanitized === '/' || sanitized === '/login') {
    return '/login'
  }
  return `/login?redirect=${encodeURIComponent(sanitized)}`
}
