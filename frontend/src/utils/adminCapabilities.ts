export type GlobalAdminRole = 'none' | 'org' | 'sub'

export interface AdminCapabilities {
  organisations: { view: boolean; create: boolean; edit: boolean }
  departments: { view: boolean; create: boolean; edit: boolean }
  support_requests: { assign: boolean }
  users: { global_manage: boolean }
  security_monitoring: { view: boolean }
  mail: { settings: boolean }
  integrations: { manage: boolean }
  system_jobs: { view: boolean }
  global_addresses: { manage: boolean }
  scope: { organisation_ids: string[]; department_root_ids: string[] }
}

export interface CapabilityGroup {
  id: string
  labelKey: string
  items: Array<{ key: string; labelKey: string }>
}

export const ADMIN_CAPABILITY_GROUPS: CapabilityGroup[] = [
  {
    id: 'organisations',
    labelKey: 'settings.adminUsers.capabilities.groups.organisations',
    items: [
      { key: 'organisations.view', labelKey: 'settings.adminUsers.capabilities.organisations.view' },
      { key: 'organisations.create', labelKey: 'settings.adminUsers.capabilities.organisations.create' },
      { key: 'organisations.edit', labelKey: 'settings.adminUsers.capabilities.organisations.edit' },
    ],
  },
  {
    id: 'departments',
    labelKey: 'settings.adminUsers.capabilities.groups.departments',
    items: [
      { key: 'departments.view', labelKey: 'settings.adminUsers.capabilities.departments.view' },
      { key: 'departments.create', labelKey: 'settings.adminUsers.capabilities.departments.create' },
      { key: 'departments.edit', labelKey: 'settings.adminUsers.capabilities.departments.edit' },
    ],
  },
  {
    id: 'support_requests',
    labelKey: 'settings.adminUsers.capabilities.groups.supportRequests',
    items: [{ key: 'support_requests.assign', labelKey: 'settings.adminUsers.capabilities.supportRequests.assign' }],
  },
  {
    id: 'users',
    labelKey: 'settings.adminUsers.capabilities.groups.users',
    items: [{ key: 'users.global_manage', labelKey: 'settings.adminUsers.capabilities.users.globalManage' }],
  },
  {
    id: 'security_monitoring',
    labelKey: 'settings.adminUsers.capabilities.groups.securityMonitoring',
    items: [{ key: 'security_monitoring.view', labelKey: 'settings.adminUsers.capabilities.securityMonitoring.view' }],
  },
]

export function emptyAdminCapabilities(): AdminCapabilities {
  return {
    organisations: { view: false, create: false, edit: false },
    departments: { view: false, create: false, edit: false },
    support_requests: { assign: false },
    users: { global_manage: false },
    security_monitoring: { view: false },
    mail: { settings: false },
    integrations: { manage: false },
    system_jobs: { view: false },
    global_addresses: { manage: false },
    scope: { organisation_ids: [], department_root_ids: [] },
  }
}

export function defaultAdminCapabilities(globalRole: GlobalAdminRole): AdminCapabilities {
  const base = emptyAdminCapabilities()
  if (globalRole === 'org') {
    return {
      ...base,
      organisations: { view: true, create: true, edit: true },
      departments: { view: true, create: true, edit: true },
      support_requests: { assign: true },
      security_monitoring: { view: true },
    }
  }
  if (globalRole === 'sub') {
    return {
      ...base,
      organisations: { view: true, create: false, edit: false },
      departments: { view: true, create: true, edit: true },
      support_requests: { assign: true },
      security_monitoring: { view: true },
    }
  }
  return base
}

export function getCapabilityValue(caps: AdminCapabilities, dotKey: string): boolean {
  const parts = dotKey.split('.')
  let node: unknown = caps
  for (const part of parts) {
    if (typeof node !== 'object' || node === null || !(part in (node as Record<string, unknown>))) {
      return false
    }
    node = (node as Record<string, unknown>)[part]
  }
  return Boolean(node)
}

/** Tiefe Kopie ohne structuredClone (Vue-Reactive-Proxies sind nicht klonbar). */
export function cloneAdminCapabilities(caps: AdminCapabilities): AdminCapabilities {
  return {
    organisations: { ...caps.organisations },
    departments: { ...caps.departments },
    support_requests: { ...caps.support_requests },
    users: { ...caps.users },
    security_monitoring: { ...caps.security_monitoring },
    mail: { ...caps.mail },
    integrations: { ...caps.integrations },
    system_jobs: { ...caps.system_jobs },
    global_addresses: { ...caps.global_addresses },
    scope: {
      organisation_ids: [...caps.scope.organisation_ids],
      department_root_ids: [...caps.scope.department_root_ids],
    },
  }
}

export function setCapabilityValue(caps: AdminCapabilities, dotKey: string, value: boolean): AdminCapabilities {
  const clone = cloneAdminCapabilities(caps)
  const parts = dotKey.split('.')
  let node: Record<string, unknown> = clone as unknown as Record<string, unknown>
  const last = parts.pop()
  if (!last) return clone
  for (const part of parts) {
    if (typeof node[part] !== 'object' || node[part] === null) {
      node[part] = {}
    }
    node = node[part] as Record<string, unknown>
  }
  node[last] = value
  return clone as AdminCapabilities
}

export function canAdminCapability(
  caps: AdminCapabilities | null | undefined,
  dotKey: string,
  isSuperAdmin = false
): boolean {
  if (isSuperAdmin) return true
  if (!caps) return false
  return getCapabilityValue(caps, dotKey)
}

export function globalRoleLabelKey(role: GlobalAdminRole | string): string {
  switch (role) {
    case 'org':
      return 'settings.adminUsers.globalRoles.org'
    case 'sub':
      return 'settings.adminUsers.globalRoles.sub'
    default:
      return 'settings.adminUsers.globalRoles.none'
  }
}

export function filterDepartmentsByAccessibleIds<T extends { id: string }>(
  departments: T[],
  accessibleIds: string[] | null | undefined
): T[] {
  if (accessibleIds == null) return departments
  const allowed = new Set(accessibleIds)
  return departments.filter((d) => allowed.has(d.id))
}

export interface AdminScopeSummaryLabels {
  all: string
  orgs: (names: string[]) => string
  depts: (names: string[]) => string
  mixed: (orgNames: string[], deptNames: string[]) => string
}

/** Kurztext für Tabellen/Listen (Org- und/oder Department-Scope). */
export function formatAdminScopeSummary(
  scope: AdminCapabilities['scope'],
  orgNameById: Map<string, string>,
  deptNameById: Map<string, string>,
  labels: AdminScopeSummaryLabels
): string {
  const orgIds = scope.organisation_ids || []
  const deptIds = scope.department_root_ids || []
  const orgNames = orgIds.map((id) => orgNameById.get(id) || id)
  const deptNames = deptIds.map((id) => deptNameById.get(id) || id)

  if (orgIds.length === 0 && deptIds.length === 0) return labels.all
  if (orgIds.length > 0 && deptIds.length === 0) return labels.orgs(orgNames)
  if (orgIds.length === 0 && deptIds.length > 0) return labels.depts(deptNames)
  return labels.mixed(orgNames, deptNames)
}

export function normalizeAdminCapabilities(raw: unknown, globalRole: GlobalAdminRole): AdminCapabilities {
  const defaults = defaultAdminCapabilities(globalRole)
  if (!raw || typeof raw !== 'object') return defaults
  const input = raw as Partial<AdminCapabilities>
  return {
    ...defaults,
    ...input,
    organisations: { ...defaults.organisations, ...(input.organisations || {}) },
    departments: { ...defaults.departments, ...(input.departments || {}) },
    support_requests: { ...defaults.support_requests, ...(input.support_requests || {}) },
    users: { ...defaults.users, ...(input.users || {}) },
    security_monitoring: { ...defaults.security_monitoring, ...(input.security_monitoring || {}) },
    mail: { ...defaults.mail, ...(input.mail || {}) },
    integrations: { ...defaults.integrations, ...(input.integrations || {}) },
    system_jobs: { ...defaults.system_jobs, ...(input.system_jobs || {}) },
    global_addresses: { ...defaults.global_addresses, ...(input.global_addresses || {}) },
    scope: {
      organisation_ids: Array.isArray(input.scope?.organisation_ids)
        ? input.scope!.organisation_ids.map(String)
        : [],
      department_root_ids: Array.isArray(input.scope?.department_root_ids)
        ? input.scope!.department_root_ids.map(String)
        : [],
    },
  }
}
