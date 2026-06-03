/**
 * Join-Code aus QR/Barcode-Text (URL mit join_code oder verschachtelt in redirect=).
 * Kein Regex auf Hostnamen — sonst wird z. B. „ematchef“ aus app.ematchef.test erkannt.
 */
export function extractJoinCodeFromScan(scannedText: string): string {
  const raw = scannedText.trim()
  if (!raw) return ''

  const fromParam = raw.match(/(?:\?|&|;|%3F|%26)join_code=([A-Za-z0-9]+)/i)
  if (fromParam?.[1]) return fromParam[1].trim()

  try {
    const url = new URL(raw)
    const topLevel = url.searchParams.get('join_code')?.trim()
    if (topLevel) return topLevel

    const redirect = url.searchParams.get('redirect')?.trim()
    if (redirect) {
      const fromRedirect = extractJoinCodeFromRedirectPath(redirect)
      if (fromRedirect) return fromRedirect
    }
  } catch {
    // kein vollständiges URL-Format
  }

  if (!raw.includes('/') && !raw.includes('.')) {
    const plain = raw.match(/^[A-Za-z0-9]{6,16}$/)
    if (plain) return plain[0]
  }

  return ''
}

export function extractJoinCodeFromRedirectPath(redirect: string): string {
  const trimmed = redirect.trim()
  if (!trimmed) return ''

  try {
    const path = trimmed.startsWith('/') ? trimmed : `/${trimmed}`
    const url = new URL(path, 'https://local.invalid')
    if (url.pathname !== '/pending-assignment') {
      const nested = trimmed.match(/join_code=([A-Za-z0-9]+)/i)
      return nested?.[1]?.trim() ?? ''
    }
    return url.searchParams.get('join_code')?.trim() ?? ''
  } catch {
    const nested = trimmed.match(/join_code=([A-Za-z0-9]+)/i)
    return nested?.[1]?.trim() ?? ''
  }
}
