/**
 * Kopplungs-QR des Fernsehers: `https://app…/connect-display/{token}`.
 * Liefert den Token aus gescanntem Text (URL oder bloss der Token) oder null.
 */
const TOKEN_RE = /^[A-Za-z0-9_-]{32,64}$/

export function extractDisplayPairingToken(text: string): string | null {
  const raw = (text || '').trim()
  if (!raw) return null
  if (TOKEN_RE.test(raw)) return raw
  try {
    const url = new URL(raw)
    const parts = url.pathname.split('/').filter(Boolean)
    if (parts.length === 2 && parts[0] === 'connect-display' && TOKEN_RE.test(parts[1] ?? '')) {
      return parts[1] as string
    }
  } catch {
    /* kein URL */
  }
  return null
}
