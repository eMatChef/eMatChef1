/** Etiketten-URL mit Material- und Chargen-Segment (/i/m/{mat}/b/{batch}). */
export function isCanonicalMaterialBatchPublicUrl(url: string | null | undefined): boolean {
  const raw = (url || '').trim()
  if (!raw) return false
  try {
    const path = new URL(raw).pathname
    return /^\/i\/m\/[^/]+\/b\/[^/]+\/?$/i.test(path)
  } catch {
    return /\/i\/m\/[^/]+\/b\/[^/]+/i.test(raw)
  }
}

/** Für Druck/Druckkorb: kanonische Material+Charge-URL (/i/m/…/b/…). */
export function isPrintableBatchPublicUrl(url: string | null | undefined): boolean {
  return isCanonicalMaterialBatchPublicUrl(url)
}

function resolvePublicQrOrigin(): string {
  const configured = (import.meta.env.VITE_QR_PUBLIC_HOST || '').trim()
  if (configured) {
    if (typeof window !== 'undefined') {
      const protocol = window.location.protocol || 'https:'
      return `${protocol}//${configured}`.replace(/\/$/, '')
    }
    return `https://${configured}`.replace(/\/$/, '')
  }
  if (typeof window !== 'undefined') {
    return window.location.origin.replace(/\/$/, '')
  }
  return ''
}

export function buildActivityPublicUrl(activityCode: string): string {
  const code = String(activityCode || '').trim()
  if (!code) return ''
  return `${resolvePublicQrOrigin()}/i/a/${encodeURIComponent(code)}`
}

/** API-URL oder aus public_code aufbauen (z. B. wenn public_url in der Antwort fehlt). */
export function resolveActivityPublicUrl(
  publicUrl: string | null | undefined,
  publicCode: string | null | undefined,
): string {
  const fromApi = String(publicUrl || '').trim()
  if (fromApi) return fromApi
  return buildActivityPublicUrl(String(publicCode || ''))
}

export function buildWorkshopPublicUrl(workshopCode: string): string {
  const code = String(workshopCode || '').trim()
  if (!code) return ''
  return `${resolvePublicQrOrigin()}/i/w/${encodeURIComponent(code)}`
}

export function resolveWorkshopPublicUrl(
  publicUrl: string | null | undefined,
  publicCode: string | null | undefined,
): string {
  const fromApi = String(publicUrl || '').trim()
  if (fromApi) return fromApi
  return buildWorkshopPublicUrl(String(publicCode || ''))
}

export function buildUserCardPublicUrl(cardCode: string): string {
  const code = String(cardCode || '').trim()
  if (!code) return ''
  return `${resolvePublicQrOrigin()}/i/c/${encodeURIComponent(code)}`
}

export function resolveUserCardPublicUrl(
  publicUrl: string | null | undefined,
  publicCode: string | null | undefined,
): string {
  const fromApi = String(publicUrl || '').trim()
  if (fromApi) return fromApi
  return buildUserCardPublicUrl(String(publicCode || ''))
}
