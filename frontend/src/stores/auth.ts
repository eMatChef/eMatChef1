import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { 
  login as apiLogin, 
  logout as apiLogout, 
  loadSession, 
  loadSessionFromServer,
  loadUserMemberships,
  refreshToken as apiRefreshToken,
  saveLastUsedDepartment as apiSaveLastUsedDepartment,
  normalizeProfile,
  type LoginResponse, 
  type UserResponse, 
  type ProfileResponse, 
  type UserDepartmentResponse
} from '@/api/auth'
import { getGeneralSettings } from '@/api/departmentSettings'
import { resetSessionExpiredHandling } from '@/api/apiClient'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<UserResponse | null>(null)
  const profile = ref<ProfileResponse | null>(null)
  const departments = ref<UserDepartmentResponse[]>([])
  const activeDepartmentId = ref<string | null>(localStorage.getItem('active_department_id'))
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const loadingUser = ref(false)
  const error = ref<string | null>(null)
  const lastSessionStartTime = ref<number>(0)
  let cookieSessionPromise: Promise<boolean> | null = null
  
  // Getters
  const isLoggedIn = computed(() => {
    // Cookie-basierte Session (Public-Seiten) hat absichtlich keinen localStorage-Token.
    return !!user.value && !!profile.value
  })

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
  
  const userColors = computed(() => {
    return {
      background: profile.value?.backgroundColor || profile.value?.background_color || '#ec4899',
      text: profile.value?.textColor || profile.value?.text_color || '#FFFFFF'
    }
  })

  // Department-Rolle des aktuellen Departments
  const currentDepartmentRole = computed(() => {
    if (!activeDepartmentId.value) return 'user'
    const dept = departments.value.find(d => d.department_id === activeDepartmentId.value)
    return dept?.role || 'user'
  })

  // Timezone des aktuellen Departments
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

  // Actions
  async function login(email: string, password: string): Promise<boolean> {
    try {
      loadingUser.value = true
      error.value = null
      departments.value = []
      activeDepartmentId.value = null
      localStorage.removeItem('active_department_id')

      const response: LoginResponse = await apiLogin(email, password)
      
      // State aktualisieren
      token.value = response.token
      user.value = {
        ...response.user,
        last_used_department: response.last_used_department ?? response.user.last_used_department ?? null
      }
      profile.value = normalizeProfile(response.profile)

      // Departments aus Login-Response verwenden
      if (response.departments && response.departments.length > 0) {
        departments.value = response.departments.map(d => ({
          department_id: d.id,
          role: d.role,
          is_primary: d.is_primary,
          department: {
            id: d.id,
            name: d.name,
            organisation_id: d.organisation_id || ''
          }
        }))

        // Primary Department bestimmen
        const lastUsedId = response.last_used_department
        const primaryId = response.primary_department
        const firstId = response.departments[0]?.id

        const newActiveDeptId = lastUsedId || primaryId || firstId || null
        activeDepartmentId.value = newActiveDeptId
        if (newActiveDeptId) localStorage.setItem('active_department_id', newActiveDeptId)
      } else {
        // Fallback: Departments separat laden
        await loadDepartments()
      }

      resetSessionExpiredHandling()
      lastSessionStartTime.value = Date.now()
      localStorage.setItem('session_last_activity_at', String(Date.now()))
      return true
    } catch (err: any) {
      console.error('Login failed:', err)
      if (err?.code === 'ECONNABORTED') {
        error.value = 'Backend antwortet nicht rechtzeitig. Bitte erneut versuchen.'
      } else {
        const fromApi =
          err?.response?.data?.error?.message ||
          err?.response?.data?.error ||
          err?.response?.data?.message
        const fromThrown = typeof err?.message === 'string' && err.message.length > 0 ? err.message : null
        error.value = fromApi || fromThrown || 'Login fehlgeschlagen'
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
    clearAuthState()
    error.value = null
  }

  async function loadUserSession(): Promise<boolean> {
    try {
      loadingUser.value = true
      error.value = null

      // Token aus localStorage holen (falls noch nicht gesetzt)
      const storedToken = localStorage.getItem('auth_token')
      const storedUserId = localStorage.getItem('user_id')
      const storedProfileId = localStorage.getItem('profile_id')
      
      // Wenn keine vollständige Session vorhanden ist, abbrechen (kein Error - ist normal)
      if (!storedToken || !storedUserId || !storedProfileId) {
        // Inkonsistenten State bereinigen
        if (storedToken && (!storedUserId || !storedProfileId)) {
          console.info('Inkonsistenter Auth-State gefunden, bereinige...')
          localStorage.removeItem('auth_token')
          localStorage.removeItem('refresh_token')
          localStorage.removeItem('user_id')
          localStorage.removeItem('profile_id')
          localStorage.removeItem('session_last_activity_at')
        }
        return false
      }
      
      // Token im Store setzen
      token.value = storedToken

      const session = await loadSession()
      
      // State aktualisieren
      user.value = session.user
      profile.value = normalizeProfile(session.profile)
      token.value = localStorage.getItem('auth_token') // Nochmal sicherstellen

      // Departments laden
      await loadDepartments()
      
      // Timezone des aktiven Departments laden
      await loadDepartmentTimezone()
      
      resetSessionExpiredHandling()
      lastSessionStartTime.value = Date.now()
      return true
    } catch (err: any) {
      // Nur bei echten API-Fehlern als Error loggen
      if (err.response) {
        console.error('Session load failed:', err)
        error.value = err.response?.data?.error?.message || 'Session konnte nicht geladen werden'
        
        // Bei 401/403: Token ist ungültig, ausloggen
        if (err.response?.status === 401 || err.response?.status === 403) {
          await logout()
        }
      } else {
        // Lokale Fehler (z.B. fehlende IDs) nur als Info loggen
        console.info('No valid session:', err.message)
      }
      
      return false
    } finally {
      loadingUser.value = false
    }
  }

  function clearAuthState(): void {
    user.value = null
    profile.value = null
    departments.value = []
    activeDepartmentId.value = null
    token.value = null
    lastSessionStartTime.value = 0
    localStorage.removeItem('auth_token')
    localStorage.removeItem('refresh_token')
    localStorage.removeItem('active_department_id')
    localStorage.removeItem('user_id')
    localStorage.removeItem('profile_id')
    localStorage.removeItem('session_last_activity_at')
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

        user.value = {
          ...session.user,
          last_used_department:
            session.last_used_department ?? session.user.last_used_department ?? null,
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
          },
        }))

        const preferredDept =
          session.last_used_department ||
          session.primary_department ||
          session.departments?.[0]?.id ||
          null
        activeDepartmentId.value = preferredDept
        if (preferredDept) {
          localStorage.setItem('active_department_id', preferredDept)
        }

        resetSessionExpiredHandling()
        lastSessionStartTime.value = Date.now()
        // IDs auf dieser Origin (z. B. qr.*) — JWT bleibt nur im HttpOnly-Cookie
        localStorage.setItem('user_id', session.user.id)
        localStorage.setItem(
          'profile_id',
          session.profile.id ?? session.user.profile_id ?? ''
        )
        return true
      })()
      return await cookieSessionPromise
    } catch {
      // Session ist nicht mehr gültig (z. B. Cookie abgelaufen): Public-UI sofort auf ausgeloggten Zustand setzen.
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

      const ids = new Set(memberships.departments.map(d => d.department_id))

      // Bereits gültige Auswahl (z. B. localStorage nach Reload) beibehalten
      if (activeDepartmentId.value && ids.has(activeDepartmentId.value)) {
        localStorage.setItem('active_department_id', activeDepartmentId.value)
        return
      }

      // Serverseitig gespeicherte Abteilung (GET /api/users/:id), falls noch Mitglied
      const lastUsed = user.value?.last_used_department
      if (lastUsed && ids.has(lastUsed)) {
        activeDepartmentId.value = lastUsed
        localStorage.setItem('active_department_id', lastUsed)
        return
      }

      const primaryDept = memberships.departments.find(d => d.is_primary)
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
    if (departments.value.find(d => d.department_id === departmentId)) {
      activeDepartmentId.value = departmentId
      localStorage.setItem('active_department_id', departmentId)
      // Timezone des neuen Departments laden
      await loadDepartmentTimezone()
      if (userId.value) {
        try {
          await apiSaveLastUsedDepartment(departmentId)
        } catch (e) {
          console.warn('last_used_department konnte nicht gespeichert werden:', e)
        }
      }
    }
  }

  /**
   * Nach Einladungs-Annahme: Mitgliedschaften neu laden und Seite vollständig neu laden,
   * damit Menü «Department wechseln» und Dropdown auf «Mein Department» erscheinen.
   */
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

  // Getter: Name des aktiven Departments
  const activeDepartmentName = computed(() => {
    if (!activeDepartmentId.value) return ''
    const dept = departments.value.find(d => d.department_id === activeDepartmentId.value)
    return dept?.department?.name || ''
  })

  function clearError(): void {
    error.value = null
  }

  /** Proaktiver Token-Refresh – verhindert 401 durch abgelaufenen JWT bei aktiver Nutzung */
  async function refreshTokenProactively(): Promise<boolean> {
    if (!isLoggedIn.value || !localStorage.getItem('refresh_token')) return false
    const MIN_SESSION_AGE = 2 * 60 * 1000 // 2 Min – kein Refresh direkt nach Login
    if (Date.now() - lastSessionStartTime.value < MIN_SESSION_AGE) return true
    try {
      const newToken = await apiRefreshToken()
      token.value = newToken
      return true
    } catch {
      return false
    }
  }

  return {
    // State
    user,
    profile,
    departments,
    activeDepartmentId,
    token,
    loadingUser,
    error,
    
    // Getters
    isLoggedIn,
    userId,
    profileId,
    userEmail,
    userDisplayName,
    userInitials,
    userRoles,
    userColors,
    currentDepartmentRole,
    activeDepartmentName,
    departmentTimezone,
    
    // Actions
    login,
    logout,
    loadUserSession,
    loadUserSessionFromCookie,
    loadDepartments,
    setActiveDepartment,
    refreshAfterInviteAccepted,
    loadDepartmentTimezone,
    clearError,
    refreshTokenProactively
  }
})
