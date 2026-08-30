import { normalizeDeptRole } from '@/utils/departmentMemberRoles'

/** Frontend-Spiegel der Backend-Matrix GrossanlassAccessRoles (§4). */

export function gaRole(role: string | null | undefined): string {
  return normalizeDeptRole(String(role || ''))
}

export function gaCanWorkMailbox(role: string | null | undefined): boolean {
  return ['mw', 'cmw', 'komm', 'spon'].includes(gaRole(role))
}

export function gaCanTakeInquiry(role: string | null | undefined): boolean {
  return ['mw', 'cmw'].includes(gaRole(role))
}

export function gaCanCreateMailDrafts(role: string | null | undefined): boolean {
  return gaRole(role) === 'mw'
}

export function gaCanSendMail(role: string | null | undefined): boolean {
  return gaRole(role) === 'mw'
}

export function gaCanConnectGmail(role: string | null | undefined): boolean {
  return gaRole(role) === 'mw'
}

export function gaCanManageProcurement(role: string | null | undefined): boolean {
  return ['mw', 'cmw'].includes(gaRole(role))
}

export function gaCanSeeAnlassOverview(role: string | null | undefined): boolean {
  return ['mw', 'cmw', 'dc'].includes(gaRole(role))
}

/** Komm/Spon: Postfach + Vorlagen, ohne Beschaffungs-Kommando. */
export function gaIsMailboxOnly(role: string | null | undefined): boolean {
  return gaCanWorkMailbox(role) && !gaCanManageProcurement(role)
}

/** Benutzer-Gefahrenzone: Dept-Rollen vergeben — nur MW. */
export function gaCanManageDepartmentUsers(role: string | null | undefined): boolean {
  return gaRole(role) === 'mw'
}

export function gaCanOperateAusgabe(role: string | null | undefined): boolean {
  return ['mw', 'cmw'].includes(gaRole(role))
}

/** Mini-Icon unten links am Ressort-Avatar (Anzeige, gesetzt unter Benutzer). */
export type GaDeptStageBadge = { short: string; role: string }

export function gaDeptStageBadge(role: string | null | undefined): GaDeptStageBadge | null {
  const r = gaRole(role)
  if (r === 'cmw') return { short: 'CM', role: r }
  if (r === 'dc') return { short: 'OK', role: r }
  if (r === 'komm') return { short: 'K', role: r }
  if (r === 'spon') return { short: 'S', role: r }
  return null
}

export function gaCanApproveEinsatz(role: string | null | undefined): boolean {
  return ['mw', 'cmw', 'dc'].includes(gaRole(role))
}

export function gaCanReleaseTrip(role: string | null | undefined): boolean {
  return ['mw', 'cmw'].includes(gaRole(role))
}

/** Router `requiredRoles` für Beschaffung ohne Postfach. */
export const GA_PROCUREMENT_ROUTE_ROLES = ['matwart', 'mw', 'cmw'] as const

/** Router: Anfragen / Vorlagen / Gmail-Inbox. */
export const GA_MAILBOX_ROUTE_ROLES = ['matwart', 'mw', 'cmw', 'komm', 'spon'] as const

/** Router: Materialübersicht / Einsätze. */
export const GA_UEBERSICHT_ROUTE_ROLES = ['matwart', 'mw', 'cmw', 'depchef', 'dc'] as const
