import type { AdminOrgOverviewUser } from '@/api/adminUsers'

export type OverviewKind = 'membership' | 'global_scope'

export interface RoleBadge {
  kind: OverviewKind
  role: string
  isPrimary: boolean
}

export type UserFrameLevel = 'org' | 'sub' | 'dept'

export interface UserRoleGroup {
  user: AdminOrgOverviewUser
  roles: RoleBadge[]
  frameLevel: UserFrameLevel
  sortRank: number
}

export interface AssignableForGroup {
  user: AdminOrgOverviewUser
  kind: OverviewKind
  role: string
  isPrimary: boolean
}

const DEPT_ROLE_ORDER: Record<string, number> = {
  mw: 10,
  dc: 11,
  l1: 12,
  l2: 13,
  l3: 14,
  u: 15,
}

/** Orgchef → Suborgchef → MW … → U */
export function roleSortRank(kind: OverviewKind, role: string): number {
  if (kind === 'global_scope' && role === 'org') return 0
  if (kind === 'global_scope' && role === 'sub') return 1
  return DEPT_ROLE_ORDER[role] ?? 99
}

export function resolveFrameLevel(roles: RoleBadge[]): UserFrameLevel {
  if (roles.some((r) => r.kind === 'global_scope' && r.role === 'org')) return 'org'
  if (roles.some((r) => r.kind === 'global_scope' && r.role === 'sub')) return 'sub'
  return 'dept'
}

export function groupAssignments(assignments: AssignableForGroup[]): UserRoleGroup[] {
  const byUser = new Map<string, AssignableForGroup[]>()
  for (const a of assignments) {
    const list = byUser.get(a.user.id) || []
    list.push(a)
    byUser.set(a.user.id, list)
  }

  const groups: UserRoleGroup[] = []
  for (const items of byUser.values()) {
    const roles: RoleBadge[] = items
      .map((a) => ({ kind: a.kind, role: a.role, isPrimary: a.isPrimary }))
      .sort((a, b) => roleSortRank(a.kind, a.role) - roleSortRank(b.kind, b.role))

    const sortRank = roles.length > 0 ? roleSortRank(roles[0].kind, roles[0].role) : 99
    groups.push({
      user: items[0].user,
      roles,
      frameLevel: resolveFrameLevel(roles),
      sortRank,
    })
  }

  return groups.sort((a, b) => a.sortRank - b.sortRank || a.user.name.localeCompare(b.user.name, 'de'))
}

export function preferredEditKind(group: UserRoleGroup): OverviewKind {
  const top = group.roles[0]
  if (!top) return 'membership'
  return top.kind === 'global_scope' ? 'global_scope' : 'membership'
}

export function scopeLabelForUser(
  user: AdminOrgOverviewUser,
  deptNameById: Map<string, string>,
  labels: { all: string; roots: (names: string[]) => string; memberOnly: string }
): string {
  const isGlobal = user.global_admin_role === 'org' || user.global_admin_role === 'sub'
  if (!isGlobal) {
    return labels.memberOnly
  }
  const rootIds = user.department_root_ids || []
  if (rootIds.length === 0) {
    return labels.all
  }
  const names = rootIds.map((id) => deptNameById.get(id) || id)
  return labels.roots(names)
}

export function badgeClassForRole(kind: OverviewKind, role: string): string {
  if (kind === 'global_scope') {
    return role === 'org' ? 'badge-global-org' : 'badge-global-sub'
  }
  return 'badge-membership'
}
