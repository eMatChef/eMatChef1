import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import {
  login as apiLogin,
  verifyMfa as apiVerifyMfa,
  isMfaChallenge,
  type MfaMethod,
  logout as apiLogout,
  loadSessionFromServer,
  loadUserMemberships,
  refreshToken as apiRefreshToken,
  saveLastUsedDepartment as apiSaveLastUsedDepartment,
  normalizeProfile,
  type LoginResponse,
  type UserResponse,
  type ProfileResponse,
  type UserDepartmentResponse,
  type AdminContextsResponse,
} from '@/api/auth'
import { getGeneralSettings } from '@/api/departmentSettings'
import { resetSessionExpiredHandling } from '@/api/apiClient'
import { clearAuthStorage } from '@/utils/authStorage'
import {
  canAdminCapability,
  defaultAdminCapabilities,
  normalizeAdminCapabilities,
  type AdminCapabilities,
  type GlobalAdminRole,
} from '@/utils/adminCapabilities'
import { markCrossSubdomainLogoutSeenFromCookie } from '@/utils/authCrossOrigin'
import {
  DEPARTMENT_CONTEXT_MARKER,
  buildAdminContextOptions,
  resolveInitialContext,
  visibleOrganisationIds,
  type AdminContextOption,
} from '@/utils/adminContext'
import {
  isActiveSupplierCompany,
  type SupplierCompanySession,
} from '@/api/supplier'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<UserResponse | null>(null)
  const profile = ref<ProfileResponse | null>(null)
  const departments = ref<UserDepartmentResponse[]>([])
  const supplierCompanies = ref<SupplierCompanySession[]>([])
  const activeSupplierCompanyId = ref<string | null>(localStorage.getItem('active_supplier_company_id'))
  const activeDepartmentId = ref<string | null>(localStorage.getItem('active_department_id'))
  /** Verwaltungskontexte laut Session (Auswahlhilfe; Rechte prüft das Backend). */
  const adminContexts = ref<AdminContextsResponse | null>(null)
  /** Aktiver Verwaltungskontext (`global` | `admin-org:<id>` | `admin-dept:<id>`); null = Department-Kontext. */
  const activeAdminContextKey = ref<string | null>(null)
  const loadingUser = ref(false)
  const error = ref<string | null>(null)
  const lastSessionStartTime = ref<number>(0)
  let cookieSessionPromise: Promise<boolean> | null = null

  const isLoggedIn = computed(() => !!user.value && !!profile.value)

  const activeSupplierCompanies = computed(() =>
    supplierCompanies.value.filter(isActiveSupplierCompany)
  )

  const hasSupplierAccess = computed(() => activeSupplierCompanies.value.length > 0)

  const isSupplierOnly = computed(
    () => hasSupplierAccess.value && departments.value.length === 0
  )

  const userId = computed(() => user.value?.id || null)
  const profileId = computed(() => profile.value?.id || null)
  const userEmail = computed(() => profile.value?.email || '')

  const userDisplayName = computed(() => {
    if (!profile.value) return ''
    if (profile.value.nickname) return profile.value.nickname
    const first = profile.value.firstName || profile.value.first_name || ''
    const last = profile.value.lastName || profile.value.last_name || ''
    if (first && last) return `${first} ${last}`.trim()
    if (first) return first
    if (last) return last
    if (profile.value.email) return profile.value.email
    return 'Unbekannt'
  })

  const userInitials = computed(() => {
    if (!profile.value) return '??'
    const explicitInitials = (profile.value.avatarInitials || profile.value.avatar_initials || '').trim()
    if (explicitInitials.length > 0) {
      return explicitInitials.slice(0, 2).toUpperCase()
    }
    const nick = (profile.value.nickname || '').trim()
    if (nick.length > 0) {
      const cleaned = nick.replace(/\s+/g, '')
      return cleaned.slice(0, 2).toUpperCase()
    }
    const first = profile.value.firstName?.charAt(0) || profile.value.first_name?.charAt(0) || ''
    const last = profile.value.lastName?.charAt(0) || profile.value.last_name?.charAt(0) || ''
    return (first + last).toUpperCase() || '??'
  })

  const userRoles = computed(() => profile.value?.roles || [])

  const availableAdminContexts = computed<AdminContextOption[]>(() =>
    buildAdminContextOptions(userRoles.value, adminContexts.value)
  )
  const activeAdminContext = computed<AdminContextOption | null>(() => {
    if (activeDepartmentId.value) return null
    return availableAdminContexts.value.find((option) => option.key === activeAdminContextKey.value) ?? null
  })
  /** true, solange statt eines Departments ein Verwaltungskontext aktiv ist (Superadmin global, Org-/Suborgchef Verwaltungsbereich). */
  const isAdminContextActive = computed(() => activeAdminContext.value !== null)

  function readStoredContext(): string | null {
    try {
      return localStorage.getItem('active_context')
    } catch {
      return null
    }
  }

  function storeContext(value: string | null): void {
    try {
      if (value) localStorage.setItem('active_context', value)
      else localStorage.removeItem('active_context')
    } catch {
      /* nur UI-Präferenz */
    }
  }

  /** Setzt Department bzw. Verwaltungskontext nach Login/Session (Schlüssel nur UI-Präferenz, nie Berechtigung). */
  function applyInitialContext(preferredDepartmentId: string | null): void {
    const initial = resolveInitialContext({
      isSuperAdmin: userRoles.value.includes('ROLE_SUPERADMIN'),
      options: availableAdminContexts.value,
      preferredDepartmentId,
      storedContext: readStoredContext(),
    })
    activeAdminContextKey.value = initial.adminContextKey
    activeDepartmentId.value = initial.activeDepartmentId
    if (initial.activeDepartmentId) {
      localStorage.setItem('active_department_id', initial.activeDepartmentId)
    } else {
      localStorage.removeItem('active_department_id')
    }
  }
  const globalAdminRole = computed<GlobalAdminRole | 'superadmin'>(() => {
    if (userRoles.value.includes('ROLE_SUPERADMIN')) return 'superadmin'
    const fromProfile = profile.value?.global_admin_role
    if (fromProfile === 'org' || fromProfile === 'sub') return fromProfile
    if (userRoles.value.includes('ROLE_ORGANISATIONSCHEF')) return 'org'
    if (userRoles.value.includes('ROLE_SUBORGCHEF')) return 'sub'
    return 'none'
  })
  const adminCapabilities = computed<AdminCapabilities | null>(() => {
    if (userRoles.value.includes('ROLE_SUPERADMIN')) {
      return defaultAdminCapabilities('org')
    }
    const role = globalAdminRole.value === 'superadmin' ? 'none' : globalAdminRole.value
    return normalizeAdminCapabilities(profile.value?.admin_capabilities, role)
  })

  function canAdmin(dotKey: string): boolean {
    return canAdminCapability(adminCapabilities.value, dotKey, userRoles.value.includes('ROLE_SUPERADMIN'))
  }

  function hasGlobalAdminAccess(): boolean {
    if (userRoles.value.includes('ROLE_SUPERADMIN')) return true
    if (globalAdminRole.value === 'org' || globalAdminRole.value === 'sub') return true
    return false
  }

  /** Sichtbarkeit (Anzeige); Zugriffsentscheidungen trifft das Backend. Ohne Zuweisung sieht ein Org-/Suborgchef keine Organisation. */
  function canAccessOrganisation(orgId: string | null | undefined): boolean {
    if (!orgId) return true
    if (userRoles.value.includes('ROLE_SUPERADMIN')) return true
    if (!hasGlobalAdminAccess()) return true
    return visibleOrganisationIds(adminContexts.value).includes(orgId)
  }

  /** null = alle Departments (Superadmin / kein Scope) */
  const accessibleDepartmentIds = computed<string[] | null>(() => {
    if (userRoles.value.includes('ROLE_SUPERADMIN')) return null
    const fromSession = profile.value?.accessible_department_ids
    if (fromSession === null || fromSession === undefined) {
      return hasGlobalAdminAccess() ? null : []
    }
    return fromSession
  })

  function canAccessDepartment(departmentId: string | null | undefined): boolean {
    if (!departmentId) return true
    if (userRoles.value.includes('ROLE_SUPERADMIN')) return true
    const ids = accessibleDepartmentIds.value
    if (ids === null) return true
    return ids.includes(departmentId)
  }

  const userColors = computed(() => ({
    background: profile.value?.backgroundColor || profile.value?.background_color || '#ec4899',
    text: profile.value?.textColor || profile.value?.text_color || '#FFFFFF',
  }))

  const currentDepartmentRole = computed(() => {
    if (!activeDepartmentId.value) return 'user'
    const dept = departments.value.find((d) => d.department_id === activeDepartmentId.value)
    return dept?.role || 'user'
  })

  function isDepartmentGrossanlass(departmentId: string | null | undefined): boolean {
    if (!departmentId) return false
    const dept = departments.value.find((d) => d.department_id === departmentId)
    return Boolean(dept?.department?.is_grossanlass)
  }

  const isActiveDepartmentGrossanlass = computed(() => isDepartmentGrossanlass(activeDepartmentId.value))

  /**
   * Grossanlass mit offener Ersteinrichtung (Freigabe fehlt). Nur ein ausdrückliches `false` zählt: Antworten ohne das Feld
   * (ältere Sitzungen, bestehende Anlässe) gelten als freigegeben. Die Sperre erzwingt der Server.
   */
  function isGrossanlassSetupPending(departmentId: string | null | undefined): boolean {
    if (!departmentId) return false
    const dept = departments.value.find((d) => d.department_id === departmentId)
    // Globale Admins (Superadmin, Orgchef, Suborgchef) behalten ihre Rechte; der Server sperrt nur Mitglieder ohne Verwaltungsscope.
    if (userRoles.value.some((r) => ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'].includes(r))) return false
    return Boolean(dept?.department?.is_grossanlass) && dept?.department?.grossanlass_config?.setup_released === false
  }

  function markGrossanlassSetupReleased(departmentId: string): void {
    const config = departments.value.find((d) => d.department_id === departmentId)?.department?.grossanlass_config
    if (config) config.setup_released = true
  }

  const isActiveGrossanlassSetupPending = computed(() => isGrossanlassSetupPending(activeDepartmentId.value))

  const departmentTimezone = ref<string>(localStorage.getItem('department_timezone') || 'Europe/Zurich')

  async function loadDepartmentTimezone() {
    if (!activeDepartmentId.value || !userId.value) return
    try {
      const settings = await getGeneralSettings(activeDepartmentId.value)
      departmentTimezone.value = settings.timezone || 'Europe/Zurich'
      localStorage.setItem('department_timezone', departmentTimezone.value)
    } catch (err) {
      console.warn('Timezone-Setting konnte nicht geladen werden, verwende Default:', err)
      departmentTimezone.value = 'Europe/Zurich'
    }
  }

  function applySupplierCompaniesFromSession(
    companies: SupplierCompanySession[] | undefined,
    lastUsedSupplierCompany: string | null | undefined
  ) {
    supplierCompanies.value = companies ?? []
    const allowed = new Set(activeSupplierCompanies.value.map((c) => c.id))
    const preferred =
      (lastUsedSupplierCompany && allowed.has(lastUsedSupplierCompany)
        ? lastUsedSupplierCompany
        : null) ||
      activeSupplierCompanies.value.find((c) => c.is_primary)?.id ||
      activeSupplierCompanies.value[0]?.id ||
      null
    activeSupplierCompanyId.value = preferred
    if (preferred) {
      localStorage.setItem('active_supplier_company_id', preferred)
    } else {
      localStorage.removeItem('active_supplier_company_id')
    }
  }

  function applyServerSession(session: NonNullable<Awaited<ReturnType<typeof loadSessionFromServer>>>) {
    user.value = {
      ...session.user,
      last_used_department:
        session.last_used_department ?? session.user.last_used_department ?? null,
      last_used_supplier_company:
        session.last_used_supplier_company ?? session.user.last_used_supplier_company ?? null,
    }
    profile.value = normalizeProfile(session.profile)
    departments.value = (session.departments || []).map((d) => ({
      department_id: d.id,
      role: d.role,
      is_primary: d.is_primary,
      department: {
        id: d.id,
        name: d.name,
        organisation_id: d.organisation_id || '',
        parent_id: d.parent_id ?? null,
        is_grossanlass: d.is_grossanlass,
        grossanlass_config: d.grossanlass_config,
      },
    }))

    applySupplierCompaniesFromSession(
      session.supplier_companies,
      session.last_used_supplier_company ?? session.user.last_used_supplier_company ?? null
    )

    adminContexts.value = session.admin_contexts ?? null
    applyInitialContext(
      session.last_used_department ||
        session.primary_department ||
        session.departments?.[0]?.id ||
        null
    )
  }

  async function applyLoginResponse(response: LoginResponse): Promise<void> {
    user.value = {
      ...response.user,
      last_used_department: response.last_used_department ?? response.user.last_used_department ?? null,
      last_used_supplier_company:
        response.last_used_supplier_company ?? response.user.last_used_supplier_company ?? null,
    }
    profile.value = normalizeProfile(response.profile)

    adminContexts.value = response.admin_contexts ?? null
    if (response.departments && response.departments.length > 0) {
      departments.value = response.departments.map((d) => ({
        department_id: d.id,
        role: d.role,
        is_primary: d.is_primary,
        department: {
          id: d.id,
          name: d.name,
          organisation_id: d.organisation_id || '',
          parent_id: d.parent_id ?? null,
          is_grossanlass: d.is_grossanlass,
          grossanlass_config: d.grossanlass_config,
        },
      }))

      applyInitialContext(
        response.last_used_department ||
          response.primary_department ||
          response.departments[0]?.id ||
          null
      )
    } else if (availableAdminContexts.value.length > 0) {
      // Ohne Mitgliedschaft, aber mit Verwaltungskontext (Superadmin, Orgchef/Suborgchef): kein Wartebereich.
      departments.value = []
      applyInitialContext(null)
    } else {
      await loadDepartments()
    }

    applySupplierCompaniesFromSession(
      response.supplier_companies,
      response.last_used_supplier_company ?? response.user.last_used_supplier_company ?? null
    )

    resetSessionExpiredHandling()
    lastSessionStartTime.value = Date.now()
    localStorage.setItem('session_last_activity_at', String(Date.now()))
    localStorage.removeItem('emat_logged_out_seen')
  }

  /** Offene MFA-Challenge (nur im Speicher): Login ist erst mit zweitem Faktor abgeschlossen. */
  const pendingMfa = ref<{ challenge: string; expiresAt: number; trustDays: number } | null>(null)

  /** Challenge aus einem externen Login (Google/MiData) übernehmen. */
  function beginMfa(challenge: string, trustDays = 90, expiresInSeconds = 300): void {
    error.value = null
    pendingMfa.value = { challenge, expiresAt: Date.now() + expiresInSeconds * 1000, trustDays }
  }

  function cancelMfa(): void {
    pendingMfa.value = null
  }

  async function completeMfa(method: MfaMethod, code: string, trustDevice = false): Promise<boolean> {
    const pending = pendingMfa.value
    if (!pending) return false
    try {
      loadingUser.value = true
      error.value = null
      const response = await apiVerifyMfa(pending.challenge, method, code.trim(), trustDevice)
      pendingMfa.value = null
      await applyLoginResponse(response)
      return true
    } catch (err: unknown) {
      const e = err as { response?: { status?: number; data?: { error?: string; code?: string } } }
      const code = e?.response?.data?.code
      // Abgelaufene/verbrauchte Challenge: zurück zum normalen Login.
      if (code === 'invalid_challenge' || code === 'inactive') {
        pendingMfa.value = null
      }
      error.value = e?.response?.data?.error || 'Bestätigung fehlgeschlagen'
      return false
    } finally {
      loadingUser.value = false
    }
  }

  async function login(email: string, password: string): Promise<boolean> {
    try {
      loadingUser.value = true
      error.value = null
      departments.value = []
      supplierCompanies.value = []
      activeDepartmentId.value = null
      activeSupplierCompanyId.value = null
      adminContexts.value = null
      activeAdminContextKey.value = null
      localStorage.removeItem('active_department_id')
      localStorage.removeItem('active_supplier_company_id')
      localStorage.removeItem('active_context')

      const response = await apiLogin(email, password)
      if (isMfaChallenge(response)) {
        // Noch keine Sitzung: erst der zweite Faktor schliesst den Login ab.
        pendingMfa.value = {
          challenge: response.challenge,
          expiresAt: Date.now() + response.expires_in * 1000,
          trustDays: response.trust_days ?? 90,
        }
        return false
      }
      await applyLoginResponse(response)
      return true
    } catch (err: unknown) {
      console.error('Login failed:', err)
      const e = err as {
        code?: string
        response?: { data?: { error?: { message?: string }; message?: string } }
        message?: string
      }
      if (e?.code === 'ECONNABORTED') {
        error.value = 'Backend antwortet nicht rechtzeitig. Bitte erneut versuchen.'
      } else {
        const fromApi =
          e?.response?.data?.error?.message ||
          e?.response?.data?.error ||
          e?.response?.data?.message
        const fromThrown = typeof e?.message === 'string' && e.message.length > 0 ? e.message : null
        error.value = (fromApi as string) || fromThrown || 'Login fehlgeschlagen'
      }
      return false
    } finally {
      loadingUser.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await apiLogout()
    } catch (err) {
      console.error('Backend logout failed:', err)
    }
    markCrossSubdomainLogoutSeenFromCookie()
    clearAuthState()
    error.value = null
  }

  function clearAuthState(): void {
    user.value = null
    profile.value = null
    departments.value = []
    supplierCompanies.value = []
    activeDepartmentId.value = null
    adminContexts.value = null
    activeAdminContextKey.value = null
    activeSupplierCompanyId.value = null
    lastSessionStartTime.value = 0
    clearAuthStorage()
  }

  /** @deprecated Nutze loadUserSessionFromCookie — Session läuft nur über HttpOnly-Cookies. */
  async function loadUserSession(): Promise<boolean> {
    return loadUserSessionFromCookie()
  }

  async function loadUserSessionFromCookie(force = false): Promise<boolean> {
    if (!force && isLoggedIn.value) return true
    if (cookieSessionPromise && !force) {
      try {
        return await cookieSessionPromise
      } catch {
        clearAuthState()
        return false
      }
    }
    try {
      loadingUser.value = true
      cookieSessionPromise = (async () => {
        const session = await loadSessionFromServer()
        if (!session) {
          clearAuthState()
          return false
        }

        applyServerSession(session)
        resetSessionExpiredHandling()
        lastSessionStartTime.value = Date.now()
        return true
      })()
      return await cookieSessionPromise
    } catch {
      clearAuthState()
      return false
    } finally {
      cookieSessionPromise = null
      loadingUser.value = false
    }
  }

  async function loadDepartments(): Promise<void> {
    try {
      if (!userId.value) return

      const memberships = await loadUserMemberships(userId.value)
      departments.value = memberships.departments

      if (memberships.departments.length === 0) {
        activeDepartmentId.value = null
        localStorage.removeItem('active_department_id')
        return
      }

      if (userRoles.value.includes('ROLE_SUPERADMIN')) {
        return
      }

      const ids = new Set(memberships.departments.map((d) => d.department_id))

      if (activeDepartmentId.value && ids.has(activeDepartmentId.value)) {
        localStorage.setItem('active_department_id', activeDepartmentId.value)
        return
      }

      const lastUsed = user.value?.last_used_department
      if (lastUsed && ids.has(lastUsed)) {
        activeDepartmentId.value = lastUsed
        localStorage.setItem('active_department_id', lastUsed)
        return
      }

      const primaryDept = memberships.departments.find((d) => d.is_primary)
      if (primaryDept) {
        activeDepartmentId.value = primaryDept.department_id
        localStorage.setItem('active_department_id', primaryDept.department_id)
      } else {
        activeDepartmentId.value = memberships.departments[0].department_id
        localStorage.setItem('active_department_id', memberships.departments[0].department_id)
      }
    } catch (err) {
      console.error('Failed to load departments:', err)
    }
  }

  async function setActiveDepartment(departmentId: string): Promise<void> {
    if (departments.value.find((d) => d.department_id === departmentId)) {
      activeDepartmentId.value = departmentId
      activeAdminContextKey.value = null
      localStorage.setItem('active_department_id', departmentId)
      storeContext(DEPARTMENT_CONTEXT_MARKER)
      await loadDepartmentTimezone()
      if (userId.value) {
        try {
          await apiSaveLastUsedDepartment(userId.value, departmentId)
        } catch (e) {
          console.warn('last_used_department konnte nicht gespeichert werden:', e)
        }
      }
    }
  }

  /**
   * Wechselt in einen Verwaltungskontext (Superadmin global, Orgchef/Suborgchef Verwaltungsbereich).
   * Verlässt den Department-Kontext, ändert aber nie Mitgliedschaften oder Rollen.
   */
  function selectAdminContext(key: string): AdminContextOption | null {
    const option = availableAdminContexts.value.find((candidate) => candidate.key === key)
    if (!option) return null
    activeAdminContextKey.value = option.key
    activeDepartmentId.value = null
    localStorage.removeItem('active_department_id')
    storeContext(option.key)
    return option
  }

  function setActiveSupplierCompany(companyId: string): void {
    if (!activeSupplierCompanies.value.some((c) => c.id === companyId)) return
    activeSupplierCompanyId.value = companyId
    localStorage.setItem('active_supplier_company_id', companyId)
  }

  function isSupplierCompanyAdmin(companyId: string): boolean {
    const company = activeSupplierCompanies.value.find((c) => c.id === companyId)
    return company?.role === 'admin'
  }

  async function refreshAfterInviteAccepted(targetDepartmentId: string): Promise<void> {
    const cookieReloaded = await loadUserSessionFromCookie(true)
    if (!cookieReloaded) {
      await loadDepartments()
    }

    const deptId =
      targetDepartmentId && departments.value.some((d) => d.department_id === targetDepartmentId)
        ? targetDepartmentId
        : departments.value[0]?.department_id

    if (!deptId) {
      window.location.reload()
      return
    }

    await setActiveDepartment(deptId)
    window.location.assign(`/${deptId}/settings/my-department`)
  }

  const activeDepartmentName = computed(() => {
    if (!activeDepartmentId.value) return ''
    const dept = departments.value.find((d) => d.department_id === activeDepartmentId.value)
    if (!dept) return ''
    const base = dept.department?.name || ''
    if (dept.department?.is_grossanlass) {
      return `${base} (Grossanlass)`
    }
    return base
  })

  const currentSupplierCompany = computed(() => {
    if (!activeSupplierCompanyId.value) return null
    return activeSupplierCompanies.value.find((c) => c.id === activeSupplierCompanyId.value) || null
  })

  const currentSupplierCompanyRole = computed(() => currentSupplierCompany.value?.role || null)

  const activeSupplierCompanyName = computed(() => currentSupplierCompany.value?.name || '')

  const isCurrentSupplierAdmin = computed(() => currentSupplierCompanyRole.value === 'admin')

  function clearError(): void {
    error.value = null
  }

  async function refreshTokenProactively(): Promise<boolean> {
    if (!isLoggedIn.value) return false
    const MIN_SESSION_AGE = 2 * 60 * 1000
    if (Date.now() - lastSessionStartTime.value < MIN_SESSION_AGE) return true
    try {
      await apiRefreshToken()
      return true
    } catch {
      return false
    }
  }

  return {
    user,
    profile,
    departments,
    supplierCompanies,
    activeSupplierCompanyId,
    activeDepartmentId,
    adminContexts,
    availableAdminContexts,
    activeAdminContext,
    isAdminContextActive,
    loadingUser,
    error,
    isLoggedIn,
    hasSupplierAccess,
    isSupplierOnly,
    activeSupplierCompanies,
    currentSupplierCompany,
    currentSupplierCompanyRole,
    activeSupplierCompanyName,
    isCurrentSupplierAdmin,
    userId,
    profileId,
    userEmail,
    userDisplayName,
    userInitials,
    userRoles,
    globalAdminRole,
    adminCapabilities,
    canAdmin,
    hasGlobalAdminAccess,
    canAccessOrganisation,
    accessibleDepartmentIds,
    canAccessDepartment,
    userColors,
    currentDepartmentRole,
    isDepartmentGrossanlass,
    isGrossanlassSetupPending,
    isActiveGrossanlassSetupPending,
    markGrossanlassSetupReleased,
    isActiveDepartmentGrossanlass,
    activeDepartmentName,
    departmentTimezone,
    login,
    pendingMfa,
    beginMfa,
    cancelMfa,
    completeMfa,
    logout,
    loadUserSession,
    loadUserSessionFromCookie,
    clearAuthState,
    loadDepartments,
    setActiveDepartment,
    selectAdminContext,
    setActiveSupplierCompany,
    isSupplierCompanyAdmin,
    refreshAfterInviteAccepted,
    loadDepartmentTimezone,
    clearError,
    refreshTokenProactively,
  }
})
