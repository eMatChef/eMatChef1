import type { useAuthStore } from '@/stores/auth'

export function parseInternalRedirectPath(raw: unknown): string | null {
  if (typeof raw !== 'string') return null
  const trimmed = raw.trim()
  if (!trimmed || !trimmed.startsWith('/') || trimmed.startsWith('//')) return null
  return trimmed
}

type AuthStoreLike = Pick<
  ReturnType<typeof useAuthStore>,
  | 'userRoles'
  | 'activeDepartmentId'
  | 'departments'
  | 'hasSupplierAccess'
  | 'activeSupplierCompanies'
  | 'activeSupplierCompanyId'
>

export function resolveDefaultSupplierPath(authStore: AuthStoreLike): string | null {
  const companies = authStore.activeSupplierCompanies
  if (companies.length === 0) return null
  const id = authStore.activeSupplierCompanyId || companies[0]?.id
  return id ? `/supplier/${id}/profile` : null
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
    return `/${primaryDepartmentId}`
  }

  if (authStore.hasSupplierAccess) {
    const supplierHome = resolveDefaultSupplierPath(authStore)
    if (supplierHome) return supplierHome
  }

  return '/pending-assignment'
}
