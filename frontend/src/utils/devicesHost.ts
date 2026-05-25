/**
 * Lager-Subdomain devices.ematchef.ch (lokal: devices.ematchef.test).
 */

const devicesHost = (import.meta.env.VITE_DEVICES_HOST || '').trim().toLowerCase()

export function getDevicesHost(): string {
  return devicesHost
}

export function isDevicesHost(): boolean {
  if (typeof window === 'undefined') return false
  const host = window.location.hostname.toLowerCase()
  if (devicesHost && host === devicesHost) return true
  return host.startsWith('devices.')
}

/** Origin der Lager-Subdomain (gleicher Port wie aktuelle Seite). */
export function getDevicesOrigin(): string {
  if (typeof window === 'undefined') return ''
  const protocol = window.location.protocol || 'http:'
  const port = window.location.port
  const host = devicesHost || (window.location.hostname.toLowerCase().startsWith('devices.')
    ? window.location.hostname.toLowerCase()
    : '')
  if (!host) return ''
  return `${protocol}//${host}${port ? `:${port}` : ''}`
}

/** Abteilungsrollen mit Lager-/Pack-Zugriff auf devices. */
export const DEVICES_WAREHOUSE_ROLES = [
  'dc',
  'depchef',
  'mw',
  'matwart',
  'sa',
  'superadmin',
  'org',
  'organisationschef',
  'sub',
  'suborgchef',
] as const

export function canAccessDevicesWarehouse(departmentRole: string | null | undefined): boolean {
  const role = String(departmentRole || '').toLowerCase().trim()
  return DEVICES_WAREHOUSE_ROLES.some((r) => r === role)
}

const PINNED_DEPT_KEY = 'devices_pinned_department_id'

export function getPinnedDepartmentId(): string | null {
  const raw = localStorage.getItem(PINNED_DEPT_KEY)
  return raw && raw.trim() !== '' ? raw.trim() : null
}

export function setPinnedDepartmentId(departmentId: string): void {
  localStorage.setItem(PINNED_DEPT_KEY, departmentId.trim())
}

export function clearPinnedDepartmentId(): void {
  localStorage.removeItem(PINNED_DEPT_KEY)
}

/**
 * Auf devices.-Host: Marketing/Admin-Pfade nicht anzeigen; Login bleibt.
 */
export function applyDevicesHostRedirects(path: string): boolean {
  if (!isDevicesHost() || typeof window === 'undefined') return false

  const mainSite = (import.meta.env.VITE_MAIN_SITE_ORIGIN || 'https://ematchef.ch').trim().replace(/\/$/, '')

  if (['/impressum', '/tos', '/blog', '/faq', '/datenschutz'].includes(path)) {
    if (mainSite) {
      window.location.replace(mainSite + (path === '/datenschutz' ? '/tos#datenschutz' : path))
      return true
    }
  }

  return false
}
