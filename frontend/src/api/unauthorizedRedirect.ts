/** Pfade, auf denen 401 keinen Login-Redirect auslösen (Formular zeigt Fehler selbst). */
export function isAuthFormPath(pathname: string): boolean {
  return (
    pathname === '/login' ||
    pathname.startsWith('/register') ||
    pathname.startsWith('/verify') ||
    pathname.includes('password-reset')
  )
}

/** Öffentliche QR-/Material-Infos ohne Login (z. B. /i/m/…, /i/b/…). */
export function isPublicAnonymousPath(pathname: string): boolean {
  if (pathname === '/open-from-qr') return true
  const parts = pathname.split('/').filter(Boolean)
  return parts[0] === 'i' && (parts[1] === 'm' || parts[1] === 'b') && parts.length >= 3
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
