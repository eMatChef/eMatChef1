/**
 * Infoscreen-Subdomain display.ematchef.ch (lokal: display.ematchef.test).
 * Auf diesem Host erscheint ausschliesslich die Infoscreen-Oberfläche.
 */

const displayHost = (import.meta.env.VITE_DISPLAY_HOST || '').trim().toLowerCase()

export function isDisplayHost(): boolean {
  if (typeof window === 'undefined') return false
  const host = window.location.hostname.toLowerCase()
  if (displayHost && host === displayHost) return true
  return host.startsWith('display.')
}

/** Origin der Display-Subdomain (gleicher Port/Protokoll wie die aktuelle Seite); leer, wenn nicht ableitbar. */
export function getDisplayOrigin(): string {
  if (typeof window === 'undefined') return ''
  const protocol = window.location.protocol || 'http:'
  const port = window.location.port
  const host = displayHost || (isDisplayHost() ? window.location.hostname.toLowerCase() : '')
  if (!host) return ''
  return `${protocol}//${host}${port ? `:${port}` : ''}`
}

/** Einstiegsseite für die manuelle ID-/Code-Eingabe. */
export function getDisplayEntryUrl(): string {
  const origin = getDisplayOrigin()
  return `${origin}/display`
}
