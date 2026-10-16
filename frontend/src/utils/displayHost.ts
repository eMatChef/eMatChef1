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

/**
 * Adresse, die auf dem Fernseher im Browser geöffnet wird (Startseite der Display-Domain mit Kopplungs-QR).
 * Quelle: die vom Backend konfigurierte Display-Origin (`display_url` vorhandener Infoscreens, `APP_DISPLAY_URL`),
 * sonst die Frontend-Konfiguration (`VITE_DISPLAY_HOST`), sonst der manuelle Einstieg auf der App-Origin. Nie hartcodiert.
 */
export function resolveKioskHomeUrl(screenDisplayUrls: readonly string[] = []): string {
  for (const raw of screenDisplayUrls) {
    try {
      const url = new URL(raw)
      return url.origin
    } catch {
      /* relative oder ungültige URL: nächste prüfen */
    }
  }
  const displayOrigin = getDisplayOrigin()
  if (displayOrigin) return displayOrigin
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  if (appOrigin) return `${appOrigin}/display`
  if (typeof window !== 'undefined') return `${window.location.origin}/display`
  return '/display'
}
