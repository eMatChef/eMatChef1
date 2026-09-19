import type { useAuthStore } from '@/stores/auth'

type AuthStore = ReturnType<typeof useAuthStore>

export type VerwaltungNavigationContext = {
  departmentId?: string
  isAdminDashboard?: boolean
}

export function getVerwaltungBasePath(ctx: VerwaltungNavigationContext = {}): string {
  if (ctx.isAdminDashboard) return '/admin-dashboard/verwaltung'
  if (ctx.departmentId) return `/${ctx.departmentId}/verwaltung`
  return '/admin-dashboard/verwaltung'
}

/** Erste für den User erreichbare Verwaltungsseite (Reihenfolge wie VerwaltungView). */
export function resolveVerwaltungLandingPath(
  authStore: AuthStore,
  ctx: VerwaltungNavigationContext = {},
): string {
  const base = getVerwaltungBasePath(ctx)
  const isAdminDashboard = ctx.isAdminDashboard ?? false
  const isSuperAdmin = authStore.userRoles.includes('ROLE_SUPERADMIN')

  const canEditGlobalTemplates =
    isSuperAdmin ||
    authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
    authStore.userRoles.includes('ROLE_SUBORGCHEF')

  if (authStore.canAdmin('global_addresses.manage')) {
    return `${base}/global-addresses`
  }
  if (authStore.canAdmin('organisations.view')) {
    return `${base}/organisations`
  }
  if (authStore.canAdmin('departments.view')) {
    return `${base}/departments`
  }
  if (authStore.canAdmin('users.global_manage')) {
    return isAdminDashboard ? `${base}/users` : '/admin-dashboard/verwaltung/users'
  }
  if (isAdminDashboard && isSuperAdmin) {
    return '/admin-dashboard/verwaltung/supplier-global-review'
  }
  if (canEditGlobalTemplates) {
    return `${base}/print-catalog`
  }
  if (authStore.canAdmin('system_jobs.view')) {
    return `${base}/jobs`
  }
  if (authStore.canAdmin('support_requests.assign')) {
    return `${base}/support-requests`
  }
  if (isAdminDashboard && authStore.canAdmin('integrations.manage')) {
    return `${base}/integrations`
  }
  if (authStore.canAdmin('security_monitoring.view')) {
    return `${base}/security-monitoring`
  }
  if (authStore.canAdmin('mail.settings')) {
    return `${base}/mail/versand`
  }
  if (authStore.hasGlobalAdminAccess()) {
    return `${base}/permissions`
  }

  return `${base}/organisations`
}
