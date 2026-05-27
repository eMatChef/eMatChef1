import type { Organisation } from '@/api/organisations'

/** Departments aus dem Auth-Store (Memberships) */
export type UserDepartmentLike = {
  department?: {
    organisation_id?: string | null
  } | null
}

/** Organisation-IDs, in denen der User mindestens ein Department hat */
export function memberOrganisationIdsFromUserDepartments(
  departments: ReadonlyArray<UserDepartmentLike> | null | undefined
): Set<string> {
  const ids = new Set<string>()
  for (const d of departments || []) {
    const oid = d.department?.organisation_id?.trim()
    if (oid) ids.add(oid)
  }
  return ids
}

/**
 * Sortierung: Organisationen mit Mitgliedschaft zuerst, danach alphabetisch nach Name.
 */
export function sortOrganisationsMembersFirst(
  orgs: Organisation[],
  memberOrganisationIds: ReadonlySet<string>
): Organisation[] {
  return [...orgs].sort((a, b) => {
    const aM = memberOrganisationIds.has(a.id) ? 0 : 1
    const bM = memberOrganisationIds.has(b.id) ? 0 : 1
    if (aM !== bM) return aM - bM
    return a.name.localeCompare(b.name, 'de', { sensitivity: 'base' })
  })
}

export type OrganisationsOrgSubAdminListOptions = {
  isSuperAdmin: boolean
  memberOrganisationIds: ReadonlySet<string>
}

/**
 * Für Organisations-/Departments-Admin-UI: Org- und Suborg-Chef sehen nur Organisationen,
 * in denen sie Mitglied sind. Superadmin sieht alle, sortiert mit „bin ich dabei“ zuerst.
 */
export function prepareOrganisationsForOrgSubAdminList(
  orgs: Organisation[],
  options: OrganisationsOrgSubAdminListOptions
): Organisation[] {
  const { isSuperAdmin, memberOrganisationIds } = options
  const scoped = isSuperAdmin ? orgs : orgs.filter((o) => memberOrganisationIds.has(o.id))
  return sortOrganisationsMembersFirst(scoped, memberOrganisationIds)
}

/** System-/J&S-Organisationen: nie in Dropdowns / Admin-Zuordnung */
export const HIDDEN_ORGANISATION_IDS = new Set(['GLOBALORG001', 'org_js000000'])

/** System-Department für globale Lieferanten-Adressen (nicht für User-Memberships) */
export const HIDDEN_DEPARTMENT_IDS = new Set(['GLOBAL000000'])

export function isOrganisationHiddenFromUserPickers(org: Organisation): boolean {
  if (HIDDEN_ORGANISATION_IDS.has(org.id)) {
    return true
  }
  const n = org.name.toLowerCase()
  if (n.includes('j&s') || n.includes('j+s')) {
    return true
  }
  if (n.includes('global system')) {
    return true
  }
  return false
}

/** Feste Reihenfolge in Registrierungs-/Join-Dropdowns: Pfadi, dann Cevi, Rest A–Z. */
function organisationUserPickerSortRank(org: Organisation): number {
  const n = org.name.toLowerCase()
  if (n.includes('pfadi')) {
    return 0
  }
  if (n.includes('cevi')) {
    return 1
  }
  return 2
}

export function sortOrganisationsForUserPickers(orgs: Organisation[]): Organisation[] {
  return [...orgs].sort((a, b) => {
    const ra = organisationUserPickerSortRank(a)
    const rb = organisationUserPickerSortRank(b)
    if (ra !== rb) {
      return ra - rb
    }
    return a.name.localeCompare(b.name, 'de', { sensitivity: 'base' })
  })
}

export function filterOrganisationsForUserPickers(orgs: Organisation[]): Organisation[] {
  const visible = orgs.filter((o) => !isOrganisationHiddenFromUserPickers(o))
  return sortOrganisationsForUserPickers(visible)
}

export type DepartmentLike = {
  id: string
  organisation_id?: string | null
  name?: string
}

/** Global Suppliers & Departments unter System-Orgs: nicht in Admin-UI / Zuteilung */
export function isDepartmentHiddenFromAdminScope(dept: DepartmentLike): boolean {
  if (HIDDEN_DEPARTMENT_IDS.has(dept.id)) {
    return true
  }
  const orgId = dept.organisation_id?.trim()
  if (orgId && HIDDEN_ORGANISATION_IDS.has(orgId)) {
    return true
  }
  const n = (dept.name || '').toLowerCase()
  if (n.includes('global suppliers') || n.includes('global system')) {
    return true
  }
  if (n.includes('j&s') || n.includes('j+s') || n.includes('leih-material')) {
    return true
  }
  return false
}

export function filterDepartmentsForAdminScope<T extends DepartmentLike>(depts: T[]): T[] {
  return depts.filter((d) => !isDepartmentHiddenFromAdminScope(d))
}

/** Gleiche Org-Filter wie bei Registrierung; für Zuteilungsübersicht & globale Admin-Rollen */
export function filterOrganisationsForAdminScope(orgs: Organisation[]): Organisation[] {
  return filterOrganisationsForUserPickers(orgs)
}
