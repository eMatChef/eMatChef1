/** Department-Rollen für Mitglieder-Verwaltung (Benutzer-Tabelle, Ressorts, Detail-Dialog). */

export const DEPT_ROLES = {
  mw: { short: 'MW', color: '#2563eb' },
  cmw: { short: 'CMW', color: '#1d4ed8' },
  dc: { short: 'DC', color: '#0891b2' },
  komm: { short: 'Komm', color: '#7c3aed' },
  spon: { short: 'Spon', color: '#c026d3' },
  l1: { short: 'L1', color: '#10b981' },
  l2: { short: 'L2', color: '#f59e0b' },
  l3: { short: 'L3', color: '#ef4444' },
  u: { short: 'U', color: '#6b7280' },
} as const

export type DeptRoleKey = keyof typeof DEPT_ROLES

/** Pfadi: Index = Rang, 0 = höchste. */
export const ROLE_HIERARCHY_PFADI: DeptRoleKey[] = ['mw', 'dc', 'l1', 'l2', 'l3', 'u']

/** Grossanlass: kein L1–L3; komm ≈ spon. */
export const ROLE_HIERARCHY_GROSSANLASS: DeptRoleKey[] = ['mw', 'cmw', 'dc', 'komm', 'spon', 'u']

/** @deprecated Nutze hierarchyForDepartment — bleibt Pfadi für Aufrufer ohne Flag. */
export const ROLE_HIERARCHY: DeptRoleKey[] = ROLE_HIERARCHY_PFADI

const PFADI_RANK: Record<string, number> = {
  mw: 0,
  dc: 1,
  l1: 2,
  l2: 3,
  l3: 4,
  u: 5,
}

const GROSSANLASS_RANK: Record<string, number> = {
  mw: 0,
  cmw: 1,
  dc: 2,
  komm: 3,
  spon: 3,
  u: 4,
}

export function hierarchyForDepartment(isGrossanlass: boolean): DeptRoleKey[] {
  return isGrossanlass ? ROLE_HIERARCHY_GROSSANLASS : ROLE_HIERARCHY_PFADI
}

export function normalizeDeptRole(role: string): string {
  const value = role.toLowerCase().trim()
  if (value === 'user') return 'u'
  if (value === 'matwart') return 'mw'
  if (value === 'co_matwart' || value === 'comatwart') return 'cmw'
  if (value === 'depchef') return 'dc'
  if (value === 'kommunikation') return 'komm'
  if (value === 'sponsoring') return 'spon'
  return value
}

function rankOf(role: string, isGrossanlass: boolean): number {
  const key = normalizeDeptRole(role)
  const ranks = isGrossanlass ? GROSSANLASS_RANK : PFADI_RANK
  return ranks[key] ?? -1
}

export function deptRoleIndex(role: string, isGrossanlass = false): number {
  return rankOf(role, isGrossanlass)
}

export function hasGlobalAdminPrivilege(userRoles: string[]): boolean {
  return (
    userRoles.includes('ROLE_SUPERADMIN')
    || userRoles.includes('ROLE_ORGANISATIONSCHEF')
    || userRoles.includes('ROLE_SUBORGCHEF')
  )
}

export function getDeptRoleColor(role: string): string {
  return DEPT_ROLES[normalizeDeptRole(role) as DeptRoleKey]?.color || '#6b7280'
}

export function getDeptRoleShort(role: string, isGrossanlass = false): string {
  const key = normalizeDeptRole(role)
  if (key === 'dc' && isGrossanlass) return 'OK-L'
  return DEPT_ROLES[key as DeptRoleKey]?.short || key.toUpperCase()
}

export function canManageDepartmentMember(opts: {
  actorUserId: string | null
  actorDeptRole: string
  actorGlobalRoles: string[]
  memberUserId: string
  memberRole: string
  isGrossanlass?: boolean
}): boolean {
  if (opts.actorUserId && opts.memberUserId === opts.actorUserId) return false
  if (hasGlobalAdminPrivilege(opts.actorGlobalRoles)) return true
  const ga = Boolean(opts.isGrossanlass)
  if (ga && normalizeDeptRole(opts.actorDeptRole) !== 'mw') return false
  const myRank = rankOf(opts.actorDeptRole || 'u', ga)
  const targetRank = rankOf(opts.memberRole, ga)
  if (myRank < 0 || targetRank < 0) return false
  return targetRank > myRank
}

export function assignableDeptRoleKeys(
  actorDeptRole: string,
  isGlobalAdmin: boolean,
  isGrossanlass = false,
): DeptRoleKey[] {
  const hierarchy = hierarchyForDepartment(isGrossanlass)
  if (isGlobalAdmin) return [...hierarchy]
  if (isGrossanlass && normalizeDeptRole(actorDeptRole) !== 'mw') return []
  const myRank = rankOf(actorDeptRole || 'u', isGrossanlass)
  if (myRank < 0) return []
  return hierarchy.filter((key) => {
    const r = rankOf(key, isGrossanlass)
    return r > myRank
  })
}
