import type { Router } from 'vue-router'

/** Aktueller Browser-Host ist die konfigurierte QR-Subdomain (z. B. qr.localhost). */
export function isQrPublicHost(): boolean {
  const qrHost = (import.meta.env.VITE_QR_PUBLIC_HOST || '').trim().toLowerCase()
  if (!qrHost || typeof window === 'undefined') return false
  return window.location.hostname.toLowerCase() === qrHost
}

/**
 * Material-Detail in der App-Instanz öffnen. Auf der QR-Subdomain ist localStorage
 * getrennt von app.* – deshalb volle Navigation zu VITE_APP_ORIGIN, sobald konfiguriert.
 */
export function navigateToAppMaterialDetail(
  router: Router,
  departmentId: string,
  materialId: string,
  batchId?: string | null
): void {
  const path = `/${departmentId}/materials/${materialId}`
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')

  if (appOrigin && isQrPublicHost()) {
    const params = new URLSearchParams()
    if (batchId) params.set('batch', batchId)
    const qs = params.toString()
    window.location.assign(`${appOrigin}${path}${qs ? `?${qs}` : ''}`)
    return
  }

  const query: Record<string, string> = {}
  if (batchId) query.batch = batchId
  void router.push({
    path,
    query: Object.keys(query).length ? query : undefined,
  })
}
