import apiClient from './apiClient'

export type SecuritySession = {
  id: string
  current: boolean
  auth_method: string
  browser: string | null
  os: string | null
  label: string
  created_at: string
  last_seen_at: string
  mfa_verified: boolean
  /** totp | recovery_code | trusted_device | null */
  mfa_source: string | null
  trusted: boolean
}

export type TrustedDevice = {
  id: string
  label: string
  trusted_at: string
  last_used_at: string | null
  valid_until: string
  current: boolean
}

export type SecurityActivityEvent = {
  action: string
  created_at: string
  detail: string | null
}

const base = (profileId: string) => `/api/profiles/${profileId}/security`

export async function getSessions(profileId: string): Promise<SecuritySession[]> {
  const { data } = await apiClient.get<{ sessions: SecuritySession[] }>(`${base(profileId)}/sessions`)
  return data.sessions
}

/** Nur andere Sitzungen; die aktuelle lehnt das Backend ab (409 current_session). */
export async function revokeSession(profileId: string, sessionId: string): Promise<SecuritySession[]> {
  const { data } = await apiClient.delete<{ sessions: SecuritySession[] }>(`${base(profileId)}/sessions/${sessionId}`)
  return data.sessions
}

/** Step-up-pflichtig (Backend: step_up_required → globaler Step-up-Dialog, danach automatischer Retry). */
export async function revokeOtherSessions(profileId: string): Promise<{ revoked: number; sessions: SecuritySession[] }> {
  const { data } = await apiClient.post<{ revoked: number; sessions: SecuritySession[] }>(
    `${base(profileId)}/sessions/revoke-others`,
  )
  return data
}

export async function getTrustedDevices(profileId: string): Promise<TrustedDevice[]> {
  const { data } = await apiClient.get<{ trusted_devices: TrustedDevice[] }>(`${base(profileId)}/trusted-devices`)
  return data.trusted_devices
}

export async function revokeTrustedDevice(profileId: string, deviceId: string): Promise<TrustedDevice[]> {
  const { data } = await apiClient.delete<{ trusted_devices: TrustedDevice[] }>(
    `${base(profileId)}/trusted-devices/${deviceId}`,
  )
  return data.trusted_devices
}

export async function getSecurityActivity(profileId: string, limit = 20): Promise<SecurityActivityEvent[]> {
  const { data } = await apiClient.get<{ events: SecurityActivityEvent[] }>(`${base(profileId)}/activity`, {
    params: { limit },
  })
  return data.events
}
