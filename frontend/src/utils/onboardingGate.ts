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

function baseDepartmentAccess(authStore: AuthStore, departmentId: string): boolean {
  if (!authStore.isLoggedIn || !departmentId || !authStore.profileId) return false
  if (skipsPersonalDepartmentOnboarding(authStore, normalizeDeptRole(authStore.currentDepartmentRole))) {
    return false
  }
  if (authStore.isDepartmentGrossanlass(departmentId)) return false
  return true
}

/** Setup-Checkliste unter Hilfe → Einrichtung (nur MW/DC). */
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

/** Hub unter Hilfe → Einrichtung (Touren und/oder Checkliste). */
export function canUseHelpEinrichtung(authStore: AuthStore, departmentId: string): boolean {
  return canUseDepartmentTours(authStore, departmentId)
}

export const HELP_EINRICHTUNG_ROLES = [
  ...DEPARTMENT_MW_DC_ROLES,
  ...DEPARTMENT_BASIC_MEMBER_ROLES,
] as const

export function isHelpEinrichtungPath(path: string, departmentId: string): boolean {
  return path === `/${departmentId}/help/einrichtung`
}

/** @deprecated use isHelpEinrichtungPath */
export function isOnboardingHubPath(path: string, departmentId: string): boolean {
  return isHelpEinrichtungPath(path, departmentId)
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
