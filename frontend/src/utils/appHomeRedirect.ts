import type { useAuthStore } from '@/stores/auth'
import { gaHomePath } from '@/utils/grossanlassHome'
import {
  ONBOARDING_TOUR_QUERY,
  ONBOARDING_TOUR_STEP_QUERY,
} from '@/config/onboardingTours'

/** Erste Path-Segmente, die kein Department-Id sind. */
const RESERVED_TOP_SEGMENTS = new Set([
  'login',
  'register',
  'verify',
  'dashboard',
  'admin-dashboard',
  'supplier',
  'pending-assignment',
  'display',
  'i',
  'open-from-qr',
  'help',
  'blog',
  'faq',
  'tos',
  'impressum',
  'datenschutz',
  'password-reset',
])

export function pathHasOnboardingTourQuery(fullPath: string): boolean {
  const q = fullPath.indexOf('?')
  if (q < 0) return false
  const params = new URLSearchParams(fullPath.slice(q + 1).split('#')[0])
  return params.has(ONBOARDING_TOUR_QUERY) || params.has(ONBOARDING_TOUR_STEP_QUERY)
}

/** `/{departmentId}/…` → `/{departmentId}` (Department-Home / Dashboard). */
export function departmentDashboardPathFromFullPath(fullPath: string): string | null {
  const pathOnly = (fullPath.split('?')[0] || '').split('#')[0] || ''
  const first = pathOnly.split('/').filter(Boolean)[0]
  if (!first || RESERVED_TOP_SEGMENTS.has(first)) return null
  return `/${first}`
}

/**
 * Redirect nach Login bereinigen:
 * Tour-Query (`onboardingTour` / Step) nie wiederherstellen → Department-Dashboard.
 */
export function sanitizeLoginRedirectPath(fullPath: string): string | null {
  const trimmed = fullPath.trim()
  if (!trimmed || !trimmed.startsWith('/') || trimmed.startsWith('//')) return null
  if (pathHasOnboardingTourQuery(trimmed)) {
    return departmentDashboardPathFromFullPath(trimmed)
  }
  return trimmed
}

export function parseInternalRedirectPath(raw: unknown): string | null {
  if (typeof raw !== 'string') return null
  return sanitizeLoginRedirectPath(raw)
}

type AuthStoreLike = Pick<
  ReturnType<typeof useAuthStore>,
  | 'userRoles'
  | 'activeDepartmentId'
  | 'departments'
  | 'hasSupplierAccess'
  | 'activeSupplierCompanies'
  | 'activeSupplierCompanyId'
  | 'currentDepartmentRole'
  | 'isDepartmentGrossanlass'
>

export function resolveDefaultSupplierPath(authStore: AuthStoreLike): string | null {
  const companies = authStore.activeSupplierCompanies
  if (companies.length === 0) return null
  const id = authStore.activeSupplierCompanyId || companies[0]?.id
  return id ? `/supplier/${id}/dashboard` : null
}

/** Ziel nach Login oder app.ematchef.ch/ — gleiche Priorität wie Router-Guard. */
export function resolveAuthenticatedHomePath(authStore: AuthStoreLike): string {
  if (authStore.userRoles?.includes('ROLE_SUPERADMIN')) {
    return '/dashboard'
  }

  const primaryDepartmentId =
    authStore.activeDepartmentId ||
    authStore.departments.find((d) => d.is_primary)?.department_id ||
    authStore.departments[0]?.department_id

  if (primaryDepartmentId) {
    if (authStore.isDepartmentGrossanlass(primaryDepartmentId)) {
      return gaHomePath(primaryDepartmentId, authStore.currentDepartmentRole)
    }
    return `/${primaryDepartmentId}`
  }

  if (authStore.hasSupplierAccess) {
    const supplierHome = resolveDefaultSupplierPath(authStore)
    if (supplierHome) return supplierHome
  }

  return '/pending-assignment'
}
