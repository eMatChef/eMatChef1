import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import {
  login as apiLogin,
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

  function canAccessOrganisation(orgId: string | null | undefined): boolean {
    if (!orgId) return true
    if (userRoles.value.includes('ROLE_SUPERADMIN')) return true
    const scoped = adminCapabilities.value?.scope?.organisation_ids || []
    if (scoped.length === 0) return true
    return scoped.includes(orgId)
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
        is_grossanlass: d.is_grossanlass,
        grossanlass_config: d.grossanlass_config,
      },
    }))

    applySupplierCompaniesFromSession(
      session.supplier_companies,
      session.last_used_supplier_company ?? session.user.last_used_supplier_company ?? null
    )

    const isSuperAdmin = (session.profile?.roles || []).includes('ROLE_SUPERADMIN')
    if (isSuperAdmin) {
      activeDepartmentId.value = null
      localStorage.removeItem('active_department_id')
      return
    }

    const preferredDept =
      session.last_used_department ||
      session.primary_department ||
      session.departments?.[0]?.id ||
      null
    activeDepartmentId.value = preferredDept
    if (preferredDept) {
      localStorage.setItem('active_department_id', preferredDept)
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
      localStorage.removeItem('active_department_id')
      localStorage.removeItem('active_supplier_company_id')

      const response: LoginResponse = await apiLogin(email, password)

      user.value = {
        ...response.user,
        last_used_department: response.last_used_department ?? response.user.last_used_department ?? null,
        last_used_supplier_company:
          response.last_used_supplier_company ?? response.user.last_used_supplier_company ?? null,
      }
      profile.value = normalizeProfile(response.profile)

      if (response.departments && response.departments.length > 0) {
        departments.value = response.departments.map((d) => ({
          department_id: d.id,
          role: d.role,
          is_primary: d.is_primary,
          department: {
            id: d.id,
            name: d.name,
            organisation_id: d.organisation_id || '',
            is_grossanlass: d.is_grossanlass,
            grossanlass_config: d.grossanlass_config,
          },
        }))

        if (!response.profile?.roles?.includes('ROLE_SUPERADMIN')) {
          const newActiveDeptId =
            response.last_used_department ||
            response.primary_department ||
            response.departments[0]?.id ||
            null
          activeDepartmentId.value = newActiveDeptId
          if (newActiveDeptId) localStorage.setItem('active_department_id', newActiveDeptId)
        }
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
      localStorage.setItem('active_department_id', departmentId)
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
    activeDepartmentName,
    departmentTimezone,
    login,
    logout,
    loadUserSession,
    loadUserSessionFromCookie,
    clearAuthState,
    loadDepartments,
    setActiveDepartment,
    setActiveSupplierCompany,
    isSupplierCompanyAdmin,
    refreshAfterInviteAccepted,
    loadDepartmentTimezone,
    clearError,
    refreshTokenProactively,
  }
})
