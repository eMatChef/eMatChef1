import { getDepartmentOnboardingStatus } from '@/api/departmentSettings'
import type { useAuthStore } from '@/stores/auth'

type AuthStore = ReturnType<typeof useAuthStore>

function normalizeDeptRole(role: string): string {
  return String(role || '').toLowerCase().trim()
}

export function canUseDepartmentOnboarding(authStore: AuthStore, departmentId: string): boolean {
  if (!authStore.isLoggedIn || !departmentId || !authStore.profileId) return false
  const role = normalizeDeptRole(authStore.currentDepartmentRole)
  if (!['dc', 'depchef', 'mw', 'matwart'].includes(role)) return false
  if (['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(role)) {
    return false
  }
  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) return false
  if (authStore.isDepartmentGrossanlass(departmentId)) return false
  return true
}

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
