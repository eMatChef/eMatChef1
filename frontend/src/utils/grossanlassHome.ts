import { gaIsBereichsleitung, gaRole } from '@/utils/grossanlassAccess'

export type GaHomeKind = 'dashboard' | 'uebersicht' | 'mailbox' | 'mein-bereich'

export type GaHomeOptions = {
  isBereichsleitung?: boolean
}

export function gaHomeKind(
  role: string | null | undefined,
  options?: GaHomeOptions,
): GaHomeKind {
  const r = gaRole(role)
  if (r === 'komm' || r === 'spon') return 'mailbox'
  if (r === 'bl' || options?.isBereichsleitung) return 'dashboard'
  if (r === 'u' || r === 'user') return 'mein-bereich'
  return 'dashboard'
}

export function gaHomePath(
  departmentId: string,
  role: string | null | undefined,
  options?: GaHomeOptions,
): string {
  const id = departmentId.replace(/^\/+|\/+$/g, '')
  switch (gaHomeKind(role, options)) {
    case 'uebersicht':
      return `/${id}/material-uebersicht`
    case 'mailbox':
      return `/${id}/beschaffung/anfragen`
    case 'mein-bereich':
      return `/${id}/mein-ressort`
    default:
      return `/${id}`
  }
}

/** Sidebar-Home ist aktiv, wenn der aktuelle Pfad zur Rollen-Heimat gehört. */
export function gaIsRoleHomePath(
  departmentId: string,
  role: string | null | undefined,
  path: string,
  options?: GaHomeOptions,
): boolean {
  const id = departmentId.replace(/^\/+|\/+$/g, '')
  const p = (path.split('?')[0] || '').replace(/\/$/, '') || '/'
  switch (gaHomeKind(role, options)) {
    case 'uebersicht':
      return p.includes(`/${id}/material-uebersicht`)
    case 'mailbox':
      return p.includes(`/${id}/beschaffung/anfragen`)
    case 'mein-bereich':
      return p.includes(`/${id}/mein-ressort`)
    default:
      return p === `/${id}` || p === `/${id}/dashboard`
  }
}

export async function gaResolveIsBereichsleitung(
  _departmentId: string,
  _userId: string | null | undefined,
  role?: string | null,
): Promise<boolean> {
  return gaIsBereichsleitung(role)
}

/** Home inkl. Bereichsleitung (Rolle `bl`), für Router-Redirects. */
export async function gaResolveHomePath(
  departmentId: string,
  role: string | null | undefined,
  _userId?: string | null | undefined,
): Promise<string> {
  return gaHomePath(departmentId, role, { isBereichsleitung: gaIsBereichsleitung(role) })
}
