import type { AdminOrgOverviewUser } from '@/api/adminUsers'
import type { Department } from '@/api/departments'
import type { UserAvatarFields } from '@/utils/userAvatar'

export function isGlobalAdminUser(user: AdminOrgOverviewUser): boolean {
  return user.global_admin_role === 'org' || user.global_admin_role === 'sub'
}

/** Org-/Subchef ohne Department-Wurzel → gilt organisationsweit (nicht auf jedem Blatt wiederholen). */
export function isOrgWideGlobalAdmin(user: AdminOrgOverviewUser): boolean {
  if (!isGlobalAdminUser(user)) return false
  return (user.department_root_ids?.length ?? 0) === 0
}

export function orgWideAdminCoversOrg(user: AdminOrgOverviewUser, orgId: string): boolean {
  if (!isOrgWideGlobalAdmin(user)) return false
  const orgIds = user.organisation_ids || []
  if (orgIds.length === 0) return true
  return orgIds.includes(orgId)
}

export function mainDepartmentIdsInOrg(orgId: string, departments: Department[]): string[] {
  return departments.filter((d) => d.organisation_id === orgId && !d.parent_id).map((d) => d.id)
}

export function orgWideAdminScopeRootIds(
  orgId: string,
  users: AdminOrgOverviewUser[],
  departments: Department[],
): Set<string> {
  const hasOrgWide = users.some((u) => orgWideAdminCoversOrg(u, orgId))
  if (!hasOrgWide) return new Set()
  return new Set(mainDepartmentIdsInOrg(orgId, departments))
}

export function orgWideAdminGroups(
  orgId: string,
  users: AdminOrgOverviewUser[],
): AdminOrgOverviewUser[] {
  return users.filter((u) => orgWideAdminCoversOrg(u, orgId))
}

export function toOverviewAvatarFields(user: AdminOrgOverviewUser): UserAvatarFields {
  return {
    name: user.name,
    first_name: user.first_name ?? null,
    last_name: user.last_name ?? null,
    nickname: user.nickname ?? null,
    avatar_initials: user.avatar_initials ?? null,
    background_color: user.background_color ?? null,
    text_color: user.text_color ?? null,
  }
}

export function orgWideFrameLevel(users: AdminOrgOverviewUser[]): 'org' | 'sub' {
  if (users.some((u) => u.global_admin_role === 'org')) return 'org'
  return 'sub'
}
