import type { AdminContextsResponse } from '@/api/auth'

/**
 * Wählbare Kontexte neben den Department-Mitgliedschaften (UserNav).
 *
 * Ein Verwaltungskontext ist keine Mitgliedschaft und vergibt keine operative Rolle (kein MW):
 *  - `global`: Superadmin-Systemkontext, kein Department.
 *  - `organisation`: ausdrücklich zugewiesene Organisation von Orgchef/Suborgchef (alle Departments darin).
 *  - `department`: ausdrücklich zugewiesene Department-Wurzel von Orgchef/Suborgchef (Unterbaum, nicht Parent/Geschwister).
 * Ohne Zuweisung gibt es keinen Kontext. Die Auswahl ist nur Navigation; Zugriff und Rechte entscheidet immer das Backend.
 */
export type AdminContextKind = 'global' | 'organisation' | 'department'

export interface AdminContextOption {
  key: string
  kind: AdminContextKind
  role: 'superadmin' | 'org' | 'sub'
  /** Wurzel-Department des Verwaltungsbereichs; null bei global/Organisation */
  departmentId: string | null
  organisationId: string | null
  name: string | null
}

export const GLOBAL_CONTEXT_KEY = 'global'
/** Marker für «zuletzt im Department-Kontext» (neben den Schlüsseln der Verwaltungskontexte). */
export const DEPARTMENT_CONTEXT_MARKER = 'department'

/** Schlüssel sind absichtlich verschieden von Department-IDs und vom Marker `department`. */
export function organisationContextKey(organisationId: string): string {
  return `admin-org:${organisationId}`
}

export function departmentContextKey(departmentId: string): string {
  return `admin-dept:${departmentId}`
}

export function buildAdminContextOptions(
  profileRoles: readonly string[],
  contexts: AdminContextsResponse | null | undefined,
): AdminContextOption[] {
  if (profileRoles.includes('ROLE_SUPERADMIN')) {
    return [
      { key: GLOBAL_CONTEXT_KEY, kind: 'global', role: 'superadmin', departmentId: null, organisationId: null, name: null },
    ]
  }
  const role = profileRoles.includes('ROLE_ORGANISATIONSCHEF')
    ? 'org'
    : profileRoles.includes('ROLE_SUBORGCHEF')
      ? 'sub'
      : null
  if (!role || !contexts || contexts.role !== role) return []

  const options: AdminContextOption[] = []
  for (const scope of contexts.scopes) {
    if (scope.kind === 'organisation') {
      options.push({
        key: organisationContextKey(scope.organisation_id),
        kind: 'organisation',
        role,
        departmentId: null,
        organisationId: scope.organisation_id,
        name: scope.name,
      })
    } else if (scope.department_id) {
      options.push({
        key: departmentContextKey(scope.department_id),
        kind: 'department',
        role,
        departmentId: scope.department_id,
        organisationId: scope.organisation_id,
        name: scope.name,
      })
    }
  }
  return options
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

/** Organisationen, die ein Org-/Suborgchef sehen darf (zugewiesen oder Organisation einer zugewiesenen Wurzel). */
export function visibleOrganisationIds(contexts: AdminContextsResponse | null | undefined): string[] {
  return [...new Set((contexts?.scopes ?? []).map((scope) => scope.organisation_id))]
}
