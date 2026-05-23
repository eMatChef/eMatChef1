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
  return !isDisplayKioskPath(path)
}

/** Öffentliche QR-/Material-Infos ohne Login (z. B. /i/m/…/b/…, /i/b/…). */
export function isPublicAnonymousPath(pathname: string): boolean {
  if (pathname === '/open-from-qr') return true
  if (isDisplayKioskPath(pathname)) return true
  const parts = pathname.split('/').filter(Boolean)
  if (parts[0] !== 'i') return false
  if (parts[1] === 'b' && parts[2]) return true
  if (parts[1] === 'm' && parts[2] && parts[3] === 'b' && parts[4]) return true
  if (parts[1] === 'm' && parts[2] && parts.length === 3) return true
  if (parts[1] === 'a' && parts[2]) return true
  return false
}

/** Kein Login-Redirect bei Session-Ablauf (Auth-Formulare + öffentliche QR-Seiten). */
export function shouldSkipLoginRedirect(pathname: string): boolean {
  return isAuthFormPath(pathname) || isPublicAnonymousPath(pathname)
}

export function loginRedirectUrl(currentFullPath?: string): string {
  const path = (currentFullPath || '').trim()
  if (!path || path === '/login' || path.startsWith('/login?')) {
    return '/login'
  }
  return `/login?redirect=${encodeURIComponent(path)}`
}
