import { getDepartmentOnboardingStatus } from '@/api/departmentSettings'
import {
  DEPARTMENT_BASIC_MEMBER_ROLES,
  DEPARTMENT_MW_DC_ROLES,
  isDepartmentBasicMemberRole,
  isDepartmentMwOrDcRole,
} from '@/composables/useDepartmentMemberRole'
import type { useAuthStore } from '@/stores/auth'

type AuthStore = ReturnType<typeof useAuthStore>

function normalizeDeptRole(role: string): string {
  return String(role || '').toLowerCase().trim()
}

function skipsPersonalDepartmentOnboarding(authStore: AuthStore, role: string): boolean {
  if (['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(role)) {
    return true
  }
  return authStore.userRoles.includes('ROLE_SUPERADMIN')
}

function isOrgOrSuborgRole(role: string): boolean {
  return ['org', 'organisationschef', 'sub', 'suborgchef', 'sa', 'superadmin'].includes(
    normalizeDeptRole(role)
  )
}

function baseDepartmentAccess(authStore: AuthStore, departmentId: string): boolean {
  if (!authStore.isLoggedIn || !departmentId || !authStore.profileId) return false
  if (skipsPersonalDepartmentOnboarding(authStore, normalizeDeptRole(authStore.currentDepartmentRole))) {
    return false
  }
  if (authStore.isDepartmentGrossanlass(departmentId)) return false
  return true
}

/** Setup-Checkliste unter Hilfe → Touren (nur MW/DC). */
export function canUseDepartmentOnboarding(authStore: AuthStore, departmentId: string): boolean {
  if (!baseDepartmentAccess(authStore, departmentId)) return false
  return isDepartmentMwOrDcRole(authStore.currentDepartmentRole)
}

/** Spotlight-Touren (MW/DC + User/L1–L3). */
export function canUseDepartmentTours(authStore: AuthStore, departmentId: string): boolean {
  if (!baseDepartmentAccess(authStore, departmentId)) return false
  const role = normalizeDeptRole(authStore.currentDepartmentRole)
  return isDepartmentMwOrDcRole(role) || isDepartmentBasicMemberRole(role)
}

/** Org-/Suborg-Admin-Touren (ohne Department-Checkliste). */
export function canUseAdminTours(authStore: AuthStore): boolean {
  if (!authStore.isLoggedIn || !authStore.profileId) return false
  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) return true
  return isOrgOrSuborgRole(authStore.currentDepartmentRole)
}

/** Hub unter Hilfe → Touren (Department- und/oder Admin-Touren). */
export function canUseHelpTours(authStore: AuthStore, departmentId: string): boolean {
  if (canUseAdminTours(authStore)) return true
  return canUseDepartmentTours(authStore, departmentId)
}

/** @deprecated use canUseHelpTours */
export const canUseHelpEinrichtung = canUseHelpTours

export const HELP_TOURS_ROLES = [
  ...DEPARTMENT_MW_DC_ROLES,
  ...DEPARTMENT_BASIC_MEMBER_ROLES,
  'org',
  'organisationschef',
  'sub',
  'suborgchef',
  'sa',
  'superadmin',
] as const

/** @deprecated use HELP_TOURS_ROLES */
export const HELP_EINRICHTUNG_ROLES = HELP_TOURS_ROLES

export function isHelpToursPath(path: string, departmentId: string): boolean {
  const normalized = path.replace(/\/$/, '') || '/'
  return (
    normalized === `/${departmentId}/help/tours` ||
    normalized === `/${departmentId}/help/einrichtung`
  )
}

/** @deprecated use isHelpToursPath */
export function isHelpEinrichtungPath(path: string, departmentId: string): boolean {
  return isHelpToursPath(path, departmentId)
}

/** @deprecated use isHelpToursPath */
export function isOnboardingHubPath(path: string, departmentId: string): boolean {
  return isHelpToursPath(path, departmentId)
}

export function isDepartmentHomePath(path: string, departmentId: string): boolean {
  return path === `/${departmentId}` || path === `/${departmentId}/` || path === `/${departmentId}/dashboard`
}

const doneCache = new Map<string, { done: boolean | null; at: number }>()
const CACHE_MS = 30_000

export async function resolveDepartmentOnboardingDone(departmentId: string): Promise<boolean | null> {
  const cached = doneCache.get(departmentId)
  if (cached && Date.now() - cached.at < CACHE_MS) {
    return cached.done
  }
  try {
    const status = await getDepartmentOnboardingStatus(departmentId)
    doneCache.set(departmentId, { done: status.doneAll, at: Date.now() })
    return status.doneAll
  } catch {
    return null
  }
}

export function invalidateOnboardingDoneCache(departmentId?: string): void {
  if (departmentId) {
    doneCache.delete(departmentId)
    return
  }
  doneCache.clear()
}
