import { normalizeDeptRole } from '@/utils/departmentMemberRoles'

/** Frontend-Spiegel der Backend-Matrix GrossanlassAccessRoles (§4). */

export function gaRole(role: string | null | undefined): string {
  return normalizeDeptRole(String(role || ''))
}

export function gaIsMaterialwart(role: string | null | undefined): boolean {
  return gaRole(role) === 'mw'
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

/** Planung/Ressorts anlassweit — nur Materialwart-Rollen, nicht OK-Leitung. */
export function gaCanManagePlanung(role: string | null | undefined): boolean {
  return ['mw', 'cmw'].includes(gaRole(role))
}

/** MW/CMW: voller Anlass-Zugriff über Dept-Rolle — keine Ressort-Flags nötig. */
export function gaDeptRoleSkipsGroupFlags(role: string | null | undefined): boolean {
  return gaCanManagePlanung(role)
}

/** OK-Leitung (`dc`): Anlass-Überblick, kein Materialbetrieb. */
export function gaIsOkLeitung(role: string | null | undefined): boolean {
  return gaRole(role) === 'dc'
}

/** Bereichsleitung (`bl`): eigener Ast, Einsätze einreichen — nicht anlassweit. */
export function gaIsBereichsleitung(role: string | null | undefined): boolean {
  return gaRole(role) === 'bl'
}

/** Helfer (`u`): Mein Ressort, eigene Einsätze, gefilterte Aufgaben — kein Planung/Postfach. */
export function gaIsGrossanlassHelper(role: string | null | undefined): boolean {
  return gaRole(role) === 'u'
}

/** Komm/Spon: Postfach + Vorlagen, ohne Beschaffungs-Kommando. */
export function gaIsMailboxOnly(role: string | null | undefined): boolean {
  return gaCanWorkMailbox(role) && !gaCanManageProcurement(role)
}

/** Benutzer-Gefahrenzone: Dept-Rollen vergeben — nur MW. */
export function gaCanManageDepartmentUsers(role: string | null | undefined): boolean {
  return gaRole(role) === 'mw'
}

/** Ressorts/Bauprojekte und Mitglieder anlassweit — MW/CMW/OK, nicht Planung. */
export function gaCanManageStruktur(role: string | null | undefined): boolean {
  return ['mw', 'cmw', 'dc'].includes(gaRole(role))
}

export function gaCanOperateAusgabe(role: string | null | undefined): boolean {
  return ['mw', 'cmw'].includes(gaRole(role))
}

/** Mini-Icon unten links am Ressort-Avatar (Anzeige, gesetzt unter Benutzer). */
export type GaDeptStageBadge = { short: string; role: string }

export function gaDeptStageBadge(role: string | null | undefined): GaDeptStageBadge | null {
  const r = gaRole(role)
  switch (r) {
    case 'mw':
      return { short: 'MW', role: r }
    case 'cmw':
      return { short: 'CMW', role: r }
    case 'dc':
      return { short: 'OK', role: r }
    case 'bl':
      return { short: 'BL', role: r }
    case 'komm':
      return { short: 'KOM', role: r }
    case 'spon':
      return { short: 'SPON', role: r }
    case 'u':
      return { short: 'H', role: r }
    default:
      return null
  }
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

/** Router: Standard-Mailtexte in Einstellungen (Postfach + OK-Überblick). */
export const GA_MAIL_SETTINGS_ROUTE_ROLES = [...GA_MAILBOX_ROUTE_ROLES, 'depchef', 'dc'] as const

export function gaCanSeeMailSettings(role: string | null | undefined): boolean {
  return gaCanWorkMailbox(role) || gaCanSeeAnlassOverview(role)
}

/** Router: Materialübersicht / Einsätze. */
export const GA_UEBERSICHT_ROUTE_ROLES = ['matwart', 'mw', 'cmw', 'depchef', 'dc'] as const

/** Router: Materialübersicht (MW/CMW/OK + Bereichsleitung). */
export const GA_MATERIAL_UEBERSICHT_ROUTE_ROLES = [...GA_UEBERSICHT_ROUTE_ROLES, 'bl'] as const

/** Router: Stammdaten-Materialien — Ansicht wie Materialübersicht, Schreiben bleibt MW/CMW. */
export const GA_MATERIALS_ROUTE_ROLES = [...GA_MATERIAL_UEBERSICHT_ROUTE_ROLES] as const

/** Router: Planung / Struktur / Freigabe. */
export const GA_PLANUNG_ROUTE_ROLES = [...GA_UEBERSICHT_ROUTE_ROLES] as const

/** Router: Meine Einsätze (Helfer). */
export const GA_HELPER_ROUTE_ROLES = ['user', 'u'] as const

/** Stern = Chef in diesem Ressort, nicht die Systemrolle BL. */
export function gaIsChefFromGroups(
  userId: string | null | undefined,
  groups: Array<{ members?: Array<{ user_id: string; is_leader?: boolean }> }>,
): boolean {
  if (!userId) return false
  return groups.some((group) =>
    group.members?.some((member) => member.user_id === userId && member.is_leader === true),
  )
}

/** @deprecated Nutze gaIsChefFromGroups — Stern ist nicht mehr BL. */
export function gaIsBereichsleitungFromGroups(
  userId: string | null | undefined,
  groups: Array<{ members?: Array<{ user_id: string; is_leader?: boolean }> }>,
): boolean {
  return gaIsChefFromGroups(userId, groups)
}

export function gaIsHelperHomeView(
  role: string | null | undefined,
  _isBereichsleitung = false,
): boolean {
  return gaIsGrossanlassHelper(role)
}

export function gaCanSeeMaterialUebersicht(
  role: string | null | undefined,
  _isBereichsleitung = false,
): boolean {
  return gaCanSeeAnlassOverview(role) || gaIsBereichsleitung(role)
}
