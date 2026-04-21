import apiClient from './apiClient'

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
  }>
  primary_department: string | null
  last_used_department: string | null
}

export interface UserResponse {
  id: string
  state: string
  profile_id: string
  /** Serverseitig gespeicherte Abteilungswahl; nur nutzen wenn noch Membership besteht */
  last_used_department?: string | null
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
  backgroundColor?: string
  textColor?: string
  background_color?: string
  text_color?: string
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
  }
}

/**
 * Login mit E-Mail und Passwort
 */
export async function login(email: string, password: string): Promise<LoginResponse> {
  const response = await apiClient.post<LoginResponse>('/api/auth/login_check', { email, password })
  const raw: unknown = response.data

  if (raw === null || raw === '' || typeof raw !== 'object') {
    if (import.meta.env.DEV) {
      console.error('[Auth] Login: unerwarteter Body (kein JSON?)', response.status, raw)
    }
    throw new Error('Ungültige Login-Antwort – prüfe ob /api auf das Symfony-Backend zeigt.')
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

  // Token und IDs im localStorage speichern
  localStorage.setItem('auth_token', data.token)
  localStorage.setItem('user_id', data.user.id)
  localStorage.setItem('profile_id', data.profile.id)
  
  // Refresh Token speichern – OHNE diesen kann bei JWT-Ablauf kein Refresh erfolgen!
  if (data.refresh_token) {
    localStorage.setItem('refresh_token', data.refresh_token)
  } else if (import.meta.env.DEV) {
    console.warn('[Auth] Login-Response enthält keinen refresh_token – Token-Refresh wird bei 401 fehlschlagen!')
  }

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
  const refreshToken = localStorage.getItem('refresh_token')
  
  // Wenn Refresh Token vorhanden, auf Server invalidieren
  if (refreshToken) {
    try {
      await apiClient.post('/api/auth/logout', { refresh_token: refreshToken })
    } catch (error) {
      console.warn('Logout auf Server fehlgeschlagen, lokale Daten werden trotzdem gelöscht:', error)
    }
  }
  
  // Lokale Auth-Daten entfernen
  localStorage.removeItem('auth_token')
  localStorage.removeItem('refresh_token')
  localStorage.removeItem('user_id')
  localStorage.removeItem('profile_id')
  localStorage.removeItem('session_last_activity_at')
}

/**
 * Lädt User-Daten anhand der gespeicherten User-ID
 */
export async function loadUser(): Promise<UserResponse> {
  const userId = localStorage.getItem('user_id')
  if (!userId) {
    throw new Error('Keine User-ID gefunden')
  }
  
  const { data } = await apiClient.get<UserResponse>(`/api/users/${userId}`)
  return data
}

/**
 * Lädt Profile-Daten anhand der gespeicherten Profile-ID
 */
export async function loadProfile(): Promise<ProfileResponse> {
  const profileId = localStorage.getItem('profile_id')
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
export async function loadSession(): Promise<{ user: UserResponse; profile: ProfileResponse }> {
  const userId = localStorage.getItem('user_id')
  const profileId = localStorage.getItem('profile_id')
  
  if (!userId || !profileId) {
    throw new Error('Keine User- oder Profile-ID gefunden')
  }
  
  const [userRes, profileRes] = await Promise.all([
    apiClient.get<UserResponse>(`/api/users/${userId}`),
    apiClient.get<ProfileResponse>(`/api/profiles/${profileId}`)
  ])
  
  return {
    user: userRes.data,
    profile: profileRes.data
  }
}

/**
 * Lädt User-Memberships (Departments + Groups)
 */
export async function loadUserMemberships(userId?: string): Promise<{ departments: UserDepartmentResponse[] }> {
  const targetUserId = userId || localStorage.getItem('user_id')
  if (!targetUserId) {
    throw new Error('Keine User-ID verfügbar')
  }
  
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
export async function setPrimaryDepartment(departmentId: string): Promise<void> {
  const userId = localStorage.getItem('user_id')
  if (!userId) {
    throw new Error('Keine User-ID verfügbar')
  }
  
  await apiClient.put(`/api/users/${userId}/set-primary-department`, {
    department_id: departmentId
  })
}

/**
 * Speichert die zuletzt aktive Abteilung (Login / Session-Wiederherstellung).
 */
export async function saveLastUsedDepartment(departmentId: string): Promise<void> {
  const userId = localStorage.getItem('user_id')
  if (!userId) {
    throw new Error('Keine User-ID verfügbar')
  }
  await apiClient.put(`/api/users/${userId}/last-used-department`, {
    department_id: departmentId
  })
}

/**
 * JWT Token Refresh
 */
export async function refreshToken(): Promise<string> {
  const refreshToken = localStorage.getItem('refresh_token')
  if (!refreshToken) {
    throw new Error('Kein Refresh Token verfügbar')
  }
  
  const { data } = await apiClient.post<{ token: string; refresh_token: string }>('/api/token/refresh', {
    refresh_token: refreshToken
  })
  
  // Neue Tokens speichern
  localStorage.setItem('auth_token', data.token)
  localStorage.setItem('refresh_token', data.refresh_token)
  
  return data.token
}

/**
 * Prüft ob User eingeloggt ist
 */
export function isAuthenticated(): boolean {
  return !!localStorage.getItem('auth_token') && !!localStorage.getItem('user_id') && !!localStorage.getItem('profile_id')
}
