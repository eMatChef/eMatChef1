/** Interne Lager-QR-URLs auf app. (nicht qr.ematchef.ch). */

function resolveAppOrigin(): string {
  const configured = (import.meta.env.VITE_APP_URL || import.meta.env.VITE_FRONTEND_URL || '').trim()
  if (configured) {
    return configured.replace(/\/$/, '')
  }
  if (typeof window !== 'undefined') {
    return window.location.origin.replace(/\/$/, '')
  }
  return ''
}

export function buildStorageAddressQrUrl(locationCode: string): string {
  const code = String(locationCode || '').trim()
  if (!code) return ''
  return `${resolveAppOrigin()}/i/l/${encodeURIComponent(code)}`
}

export function buildStorageRackQrUrl(rackCode: string): string {
  const code = String(rackCode || '').trim()
  if (!code) return ''
  return `${resolveAppOrigin()}/i/r/${encodeURIComponent(code)}`
}

export function buildStorageSlotQrUrl(slotCode: string): string {
  const code = String(slotCode || '').trim()
  if (!code) return ''
  return `${resolveAppOrigin()}/i/s/${encodeURIComponent(code)}`
}

export function isInternalStorageQrUrl(url: string | null | undefined): boolean {
  const raw = (url || '').trim()
  if (!raw) return false
  try {
    const path = new URL(raw).pathname
    return /^\/i\/(l|r|s)\/[^/]+\/?$/i.test(path)
  } catch {
    return /\/i\/(l|r|s)\/[^/]+/i.test(raw)
  }
}
