import { gaRole } from '@/utils/grossanlassAccess'

export type GaHomeKind = 'dashboard' | 'uebersicht' | 'mailbox' | 'mein-bereich'

export function gaHomeKind(role: string | null | undefined): GaHomeKind {
  const r = gaRole(role)
  if (r === 'dc') return 'uebersicht'
  if (r === 'komm' || r === 'spon') return 'mailbox'
  if (r === 'u' || r === 'user') return 'mein-bereich'
  return 'dashboard'
}

export function gaHomePath(departmentId: string, role: string | null | undefined): string {
  const id = departmentId.replace(/^\/+|\/+$/g, '')
  switch (gaHomeKind(role)) {
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
): boolean {
  const id = departmentId.replace(/^\/+|\/+$/g, '')
  const p = (path.split('?')[0] || '').replace(/\/$/, '') || '/'
  switch (gaHomeKind(role)) {
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
