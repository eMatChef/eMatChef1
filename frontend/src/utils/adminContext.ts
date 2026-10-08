import type { AdminContextsResponse } from '@/api/auth'

/**
 * Wählbare Kontexte neben den Department-Mitgliedschaften (UserNav).
 *
 * Ein Verwaltungskontext ist keine Mitgliedschaft und vergibt keine operative Rolle (kein MW):
 *  - `global`: Superadmin-Systemkontext, kein Department.
 *  - `management:<departmentId>`: Verwaltungsbereich von Orgchef/Suborgchef (Wurzel-Department, Unterbaum inklusive).
 *  - `management:all`: Orgchef/Suborgchef ohne Scope-Einschränkung.
 * Die Auswahl ist nur Navigation; Zugriff und Rechte entscheidet immer das Backend.
 */
export type AdminContextKind = 'global' | 'management'

export interface AdminContextOption {
  key: string
  kind: AdminContextKind
  role: 'superadmin' | 'org' | 'sub'
  /** Wurzel-Department des Verwaltungsbereichs; null bei global/unbeschränkt */
  departmentId: string | null
  name: string | null
  organisationId: string | null
}

export const GLOBAL_CONTEXT_KEY = 'global'
/** Marker für «zuletzt im Department-Kontext» (neben den Schlüsseln der Verwaltungskontexte). */
export const DEPARTMENT_CONTEXT_MARKER = 'department'

export function managementContextKey(departmentId: string | null): string {
  return `management:${departmentId ?? 'all'}`
}

export function buildAdminContextOptions(
  profileRoles: readonly string[],
  contexts: AdminContextsResponse | null | undefined,
): AdminContextOption[] {
  if (profileRoles.includes('ROLE_SUPERADMIN')) {
    return [
      { key: GLOBAL_CONTEXT_KEY, kind: 'global', role: 'superadmin', departmentId: null, name: null, organisationId: null },
    ]
  }
  const role = profileRoles.includes('ROLE_ORGANISATIONSCHEF')
    ? 'org'
    : profileRoles.includes('ROLE_SUBORGCHEF')
      ? 'sub'
      : null
  if (!role || !contexts || (contexts.role !== 'org' && contexts.role !== 'sub')) return []

  if (contexts.scopes.length > 0) {
    return contexts.scopes.map((scope) => ({
      key: managementContextKey(scope.department_id),
      kind: 'management' as const,
      role,
      departmentId: scope.department_id,
      name: scope.name,
      organisationId: scope.organisation_id,
    }))
  }
  if (contexts.unrestricted) {
    return [{ key: managementContextKey(null), kind: 'management', role, departmentId: null, name: null, organisationId: null }]
  }
  return []
}

export interface InitialContextInput {
  isSuperAdmin: boolean
  options: AdminContextOption[]
  /** Department aus Session (zuletzt benutzt → primär → erstes); null ohne Mitgliedschaft */
  preferredDepartmentId: string | null
  /** Zuletzt gewählter Kontext dieses Browsers (nur UI-Präferenz, nie Berechtigung) */
  storedContext: string | null
}

export interface InitialContext {
  activeDepartmentId: string | null
  /** null = Department-Kontext */
  adminContextKey: string | null
}

/**
 * Startkontext nach Login/Session. Superadmin startet global (wie bisher), ausser er hat zuletzt ein Department
 * gewählt. Orgchef/Suborgchef starten im Department, ausser ohne Mitgliedschaft oder mit gespeichertem Verwaltungsbereich.
 */
export function resolveInitialContext(input: InitialContextInput): InitialContext {
  const { isSuperAdmin, options, preferredDepartmentId, storedContext } = input
  const department: InitialContext = { activeDepartmentId: preferredDepartmentId, adminContextKey: null }
  if (options.length === 0) return department

  const stored = options.find((option) => option.key === storedContext)
  if (isSuperAdmin) {
    if (storedContext === DEPARTMENT_CONTEXT_MARKER && preferredDepartmentId) return department
    return { activeDepartmentId: null, adminContextKey: options[0].key }
  }
  if (stored) return { activeDepartmentId: null, adminContextKey: stored.key }
  if (!preferredDepartmentId) return { activeDepartmentId: null, adminContextKey: options[0].key }
  return department
}

/** Startseite des Verwaltungskontexts (Superadmin: globales Dashboard, Orgchef/Suborgchef: Verwaltung). */
export function adminContextHomePath(option: Pick<AdminContextOption, 'kind'>): string {
  return option.kind === 'global' ? '/dashboard' : '/admin-dashboard/verwaltung'
}
