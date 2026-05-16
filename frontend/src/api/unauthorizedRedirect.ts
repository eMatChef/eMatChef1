/** Pfade, auf denen 401 keinen Login-Redirect auslösen (Formular zeigt Fehler selbst). */
export function isAuthFormPath(pathname: string): boolean {
  return (
    pathname === '/login' ||
    pathname.startsWith('/register') ||
    pathname.startsWith('/verify') ||
    pathname.includes('password-reset')
  )
}

export function loginRedirectUrl(currentFullPath?: string): string {
  const path = (currentFullPath || '').trim()
  if (!path || path === '/login' || path.startsWith('/login?')) {
    return '/login'
  }
  return `/login?redirect=${encodeURIComponent(path)}`
}
