import apiClient, { refreshSessionCookie } from './apiClient'
import { clearAuthStorage, purgeLegacyAuthSecrets } from '@/utils/authStorage'
import { markCrossSubdomainLogoutSeenFromCookie } from '@/utils/authCrossOrigin'
import type { SupplierCompanySession } from '@/api/supplier'

export interface LoginRequest {
  email: string
  password: string
}

export interface RegisterRequest {
  firstName: string
  lastName: string
  nickname?: string
  email: string
  password: string
  language: string
  acceptTerms: boolean
  requestedOrganisationId: string
  requestedDepartmentName: string
  /** Bestehende Abteilung aus Suche – MW/DC muss bestaetigen */
  requestedDepartmentId?: string
  /** Uebergeordnete Abteilung bei Admin-Antrag (wenn Abteilung nicht gefunden) */
  requestedParentDepartmentId?: string
  requestedParentDepartmentName?: string
  /** Cloudflare Turnstile (wenn VITE_TURNSTILE_SITE_KEY gesetzt) */
  turnstileToken?: string
  /** Bot-Schutz: muss leer bleiben */
  website?: string
}

export interface RegisterResponse {
  success: boolean
  message: string
}

export interface VerifyEmailResponse {
  success: boolean
  message?: string
  error?: string
}

export interface PasswordResetRequestResponse {
  success: boolean
  message: string
}

export interface PasswordResetConfirmResponse {
  success: boolean
  message: string
}

export interface LoginResponse {
  token: string
  refresh_token?: string
  user: {
    id: string
    state: string
    profile_id: string
    last_used_department?: string | null
    last_used_supplier_company?: string | null
  }
  profile: {
    id: string
    email: string
    firstName?: string
    lastName?: string
    first_name?: string
    last_name?: string
    nickname?: string
    avatarInitials?: string
    avatar_initials?: string
    pendingEmail?: string
    pending_email?: string
    language: string
    roles: string[]
    backgroundColor?: string
    textColor?: string
    background_color?: string
    text_color?: string
  }
  departments: Array<{
    id: string
    name: string
    organisation_id: string
    role: string
    is_primary: boolean
    is_grossanlass?: boolean
    grossanlass_config?: {
      status: string
      planned_event_start: string
      planned_event_end?: string | null
      main_activity_id?: string | null
    }
  }>
  primary_department: string | null
  last_used_department: string | null
  supplier_companies?: SupplierCompanySession[]
  last_used_supplier_company?: string | null
}

export interface ServerSessionResponse {
  user: LoginResponse['user']
  profile: LoginResponse['profile']
  departments: LoginResponse['departments']
  primary_department: string | null
  last_used_department: string | null
  supplier_companies?: SupplierCompanySession[]
  last_used_supplier_company?: string | null
}

export interface UserResponse {
  id: string
  state: string
  profile_id: string
  /** Serverseitig gespeicherte Abteilungswahl; nur nutzen wenn noch Membership besteht */
  last_used_department?: string | null
  last_used_supplier_company?: string | null
}

export interface ProfileResponse {
  id: string
  email: string
  firstName?: string
  lastName?: string
  first_name?: string
  last_name?: string
  nickname?: string
  avatarInitials?: string
  avatar_initials?: string
  pendingEmail?: string
  pending_email?: string
  language: string
  roles: string[]
  global_admin_role?: string
  admin_capabilities?: import('@/utils/adminCapabilities').AdminCapabilities
  accessible_department_ids?: string[] | null
  backgroundColor?: string
  textColor?: string
  background_color?: string
  text_color?: string
}

/** API liefert snake_case; Store/UI nutzen beides – einheitlich normalisieren. */
export function normalizeProfile(
  raw: LoginResponse['profile'] | ProfileResponse
): ProfileResponse {
  return {
    ...raw,
    firstName: raw.firstName ?? raw.first_name ?? undefined,
    lastName: raw.lastName ?? raw.last_name ?? undefined,
    avatarInitials: raw.avatarInitials ?? raw.avatar_initials ?? undefined,
    pendingEmail: raw.pendingEmail ?? raw.pending_email ?? undefined,
    backgroundColor: raw.backgroundColor ?? raw.background_color ?? undefined,
    textColor: raw.textColor ?? raw.text_color ?? undefined,
  }
}

export interface UpdateProfilePayload {
  email?: string
  first_name?: string
  last_name?: string
  nickname?: string
  avatar_initials?: string
  language?: string
  background_color?: string
  text_color?: string
}

export interface ChangePasswordPayload {
  current_password: string
  new_password: string
  confirm_new_password: string
}

export interface UserDepartmentResponse {
  department_id: string
  role: string
  is_primary?: boolean
  department: {
    id: string
    name: string
    organisation_id: string
    is_grossanlass?: boolean
    grossanlass_config?: {
      status: string
      planned_event_start: string
      planned_event_end?: string | null
      main_activity_id?: string | null
    }
  }
}

/**
 * Login mit E-Mail und Passwort
 */
export async function login(email: string, password: string): Promise<LoginResponse> {
  const response = await apiClient.post<LoginResponse>('/api/auth/login_check', { email, password })
  const raw: unknown = response.data

  if (typeof raw === 'string') {
    const head = raw.slice(0, 120).toLowerCase()
    if (head.includes('<!doctype') || head.includes('<html')) {
      throw new Error(
        `Login: API lieferte HTML statt JSON (HTTP ${response.status}). Häufig Nginx/502 oder falscher Proxy — prüfe api-dev → Backend :8081 und Container-Logs.`
      )
    }
    if (import.meta.env.DEV) {
      console.error('[Auth] Login: Text-Body statt JSON', response.status, raw.slice(0, 200))
    }
    throw new Error(
      `Ungültige Login-Antwort (HTTP ${response.status}) — Body ist kein JSON. Prüfe, ob /api auf das Symfony-Backend zeigt.`
    )
  }

  if (raw === null || raw === '' || typeof raw !== 'object') {
    if (import.meta.env.DEV) {
      console.error('[Auth] Login: unerwarteter Body (kein JSON?)', response.status, raw)
    }
    throw new Error(
      `Ungültige Login-Antwort (HTTP ${response.status}) — prüfe ob /api auf das Symfony-Backend zeigt.`
    )
  }

  const body = raw as LoginResponse & { access_token?: string }
  const token =
    typeof body.token === 'string' && body.token.length > 0
      ? body.token
      : typeof body.access_token === 'string' && body.access_token.length > 0
        ? body.access_token
        : null

  if (!token) {
    if (import.meta.env.DEV) {
      console.error('[Auth] Login-Body ohne token:', body)
    }
    throw new Error('Keine Token in Login-Antwort')
  }
  if (!body.user?.id) {
    console.error('Login response missing user:', body)
    throw new Error('User-Daten fehlen in Login-Antwort')
  }
  if (!body.profile?.id) {
    console.error('Login response missing profile:', body)
    throw new Error('Profil-Daten fehlen in Login-Antwort')
  }

  const data: LoginResponse = { ...body, token }

  // JWT + Refresh nur in HttpOnly-Cookies (Lexik/Gesdinet) — nichts in localStorage.
  purgeLegacyAuthSecrets()

  return data
}

/**
 * Registrierung eines neuen Benutzers
 */
export async function register(payload: RegisterRequest): Promise<RegisterResponse> {
  const { data } = await apiClient.post<RegisterResponse>('/api/auth/register', payload)
  return data
}

export async function verifyEmail(token: string): Promise<VerifyEmailResponse> {
  const { data } = await apiClient.get<VerifyEmailResponse>('/api/auth/verify', {
    params: { token }
  })
  return data
}

export async function resendVerification(email: string): Promise<RegisterResponse> {
  const { data } = await apiClient.post<RegisterResponse>('/api/auth/resend-verification', { email })
  return data
}

export async function requestPasswordReset(email: string): Promise<PasswordResetRequestResponse> {
  const { data } = await apiClient.post<PasswordResetRequestResponse>('/api/auth/password-reset/request', { email })
  return data
}

export async function confirmPasswordReset(
  email: string,
  code: string,
  newPassword: string
): Promise<PasswordResetConfirmResponse> {
  const { data } = await apiClient.post<PasswordResetConfirmResponse>('/api/auth/password-reset/confirm', {
    email,
    code,
    newPassword
  })
  return data
}

/**
 * Logout - invalidiert Refresh Token auf dem Server und löscht lokale Daten
 */
export async function logout(): Promise<void> {
  try {
    await apiClient.post('/api/auth/logout')
  } catch (error) {
    console.warn('Logout auf Server fehlgeschlagen, lokale Daten werden trotzdem gelöscht:', error)
  }

  markCrossSubdomainLogoutSeenFromCookie()
  clearAuthStorage()
}

/**
 * Lädt User-Daten anhand der gespeicherten User-ID
 */
export async function loadUser(userId: string): Promise<UserResponse> {
  if (!userId) {
    throw new Error('Keine User-ID gefunden')
  }

  const { data } = await apiClient.get<UserResponse>(`/api/users/${userId}`)
  return data
}

/**
 * Lädt Profile-Daten anhand der gespeicherten Profile-ID
 */
export async function loadProfile(profileId: string): Promise<ProfileResponse> {
  if (!profileId) {
    throw new Error('Keine Profile-ID gefunden')
  }

  const { data } = await apiClient.get<ProfileResponse>(`/api/profiles/${profileId}`)
  return data
}

export async function updateProfile(
  profileId: string,
  payload: UpdateProfilePayload
): Promise<ProfileResponse> {
  const { data } = await apiClient.patch<ProfileResponse>(`/api/profiles/${profileId}`, payload)
  return data
}

export async function changePassword(
  profileId: string,
  payload: ChangePasswordPayload
): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.patch<{ success: boolean; message: string }>(`/api/profiles/${profileId}/password`, payload)
  return data
}

/**
 * Lädt User- und Profile-Daten parallel
 */
export async function loadSession(
  userId: string,
  profileId: string,
): Promise<{ user: UserResponse; profile: ProfileResponse }> {
  if (!userId || !profileId) {
    throw new Error('Keine User- oder Profile-ID gefunden')
  }

  const [userRes, profileRes] = await Promise.all([
    apiClient.get<UserResponse>(`/api/users/${userId}`),
    apiClient.get<ProfileResponse>(`/api/profiles/${profileId}`),
  ])

  return {
    user: userRes.data,
    profile: profileRes.data,
  }
}

/**
 * Lädt Session rein über serverseitige Auth-Cookies (ohne localStorage IDs).
 * 401 = nicht eingeloggt (kein Fehler werfen — optionaler Probe-Call).
 */
export async function loadSessionFromServer(): Promise<ServerSessionResponse | null> {
  try {
    const { data } = await apiClient.get<ServerSessionResponse>('/api/auth/session')
    return data
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 401 || status === 403) {
      return null
    }
    throw err
  }
}

/**
 * Lädt User-Memberships (Departments + Groups)
 */
export async function loadUserMemberships(userId: string): Promise<{ departments: UserDepartmentResponse[] }> {
  if (!userId) {
    throw new Error('Keine User-ID verfügbar')
  }
  const targetUserId = userId
  
  const { data } = await apiClient.get<{ memberships: any[] }>(`/api/users/${targetUserId}/memberships`)
  
  // API gibt { memberships: [...] } zurück, nicht direkt ein Array
  const memberships = data.memberships || []
  
  // Filter nur Department-Memberships (haben department_id)
  const departments = memberships.filter(m => m.department_id) as UserDepartmentResponse[]
  
  return { departments }
}

/**
 * Setzt das primäre Department für den User in der DB
 */
export async function setPrimaryDepartment(userId: string, departmentId: string): Promise<void> {
  if (!userId) {
    throw new Error('Keine User-ID verfügbar')
  }

  await apiClient.put(`/api/users/${userId}/set-primary-department`, {
    department_id: departmentId,
  })
}

/**
 * Speichert die zuletzt aktive Abteilung (Login / Session-Wiederherstellung).
 */
export async function saveLastUsedDepartment(userId: string, departmentId: string): Promise<void> {
  if (!userId) {
    throw new Error('Keine User-ID verfügbar')
  }
  await apiClient.put(`/api/users/${userId}/last-used-department`, {
    department_id: departmentId,
  })
}

/**
 * JWT-Refresh über HttpOnly refresh_token-Cookie (Gesdinet).
 */
export async function refreshToken(): Promise<void> {
  const ok = await refreshSessionCookie()
  if (!ok) {
    throw new Error('Token refresh failed')
  }
}
