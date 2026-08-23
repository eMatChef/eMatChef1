import type { Router } from 'vue-router'
import { isQrPublicHost, resolvePublicLinkOrigin } from '@/utils/appLoginUrl'

export { isQrPublicHost }

/**
 * Material-Detail in der App öffnen. Auf der QR-Subdomain volle Navigation zur Hauptdomain
 * (ematchef.*), nicht app.* — localStorage ist origin-gebunden.
 */
export function navigateToAppMaterialDetail(
  router: Router,
  departmentId: string,
  materialId: string,
  batchId?: string | null
): void {
  const path = `/${departmentId}/materials/${materialId}`
  const linkOrigin = resolvePublicLinkOrigin()

  if (linkOrigin && isQrPublicHost()) {
    const params = new URLSearchParams()
    if (batchId) params.set('batch', batchId)
    const qs = params.toString()
    window.location.assign(`${linkOrigin}${path}${qs ? `?${qs}` : ''}`)
    return
  }

  const query: Record<string, string> = {}
  if (batchId) query.batch = batchId
  void router.push({
    path,
    query: Object.keys(query).length ? query : undefined,
  })
}

/**
 * Aktivitäts-Detail in der App öffnen (von qr.ematchef.ch).
 */
export function navigateToAppActivityDetail(
  router: Router,
  departmentId: string,
  activityId: string,
): void {
  const path = `/${departmentId}/activities/${activityId}`
  const linkOrigin = resolvePublicLinkOrigin()

  if (linkOrigin && isQrPublicHost()) {
    window.location.assign(`${linkOrigin}${path}`)
    return
  }

  void router.push(path)
}

/**
 * Werkstatt-Ticket in der App öffnen (von qr.ematchef.ch) — Liste mit Ticket-Modal.
 */
export function navigateToAppWorkshopTicket(
  router: Router,
  departmentId: string,
  ticketId: string,
): void {
  const path = `/${departmentId}/workshop`
  const query = `?ticket=${encodeURIComponent(ticketId)}`
  const linkOrigin = resolvePublicLinkOrigin()

  if (linkOrigin && isQrPublicHost()) {
    window.location.assign(`${linkOrigin}${path}${query}`)
    return
  }

  void router.push({ path, query: { ticket: ticketId } })
}

export function navigateToAppGrossanlassCards(
  router: Router,
  departmentId: string,
): void {
  const path = `/${departmentId}/einstellungen/karten`
  const linkOrigin = resolvePublicLinkOrigin()

  if (linkOrigin && isQrPublicHost()) {
    window.location.assign(`${linkOrigin}${path}`)
    return
  }

  void router.push(path)
}
