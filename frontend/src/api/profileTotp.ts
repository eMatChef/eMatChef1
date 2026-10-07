import apiClient from './apiClient'

export type TotpStatus = {
  enabled: boolean
  /** Verpflichtend wegen globaler Adminrolle */
  required: boolean
  /** Verpflichtend, aber noch nicht eingerichtet: geschützte Adminfunktionen bleiben gesperrt */
  admin_blocked: boolean
  can_disable: boolean
  pending_enrollment: boolean
  locked: boolean
  recovery_codes_total: number
  recovery_codes_remaining: number | null
  recovery_codes_low: boolean
  recovery_codes_empty: boolean
}

export type TotpEnrollment = {
  /** Manueller Setup-Key; nur in dieser Antwort enthalten */
  secret: string
  otpauth_uri: string
  status: TotpStatus
}

export type TotpRecoveryResult = {
  /** Recovery Codes im Klartext; nur in dieser Antwort enthalten */
  recovery_codes: string[]
  status: TotpStatus
}

const base = (profileId: string) => `/api/profiles/${profileId}/security/totp`

export async function getTotpStatus(profileId: string): Promise<TotpStatus> {
  const { data } = await apiClient.get<TotpStatus>(base(profileId))
  return data
}

/** code nur nötig, wenn TOTP bereits aktiv ist (Neueinrichtung). */
export async function startTotpEnrollment(profileId: string, code?: string): Promise<TotpEnrollment> {
  const { data } = await apiClient.post<TotpEnrollment>(`${base(profileId)}/enroll`, { code })
  return data
}

export async function confirmTotpEnrollment(profileId: string, code: string): Promise<TotpRecoveryResult> {
  const { data } = await apiClient.post<TotpRecoveryResult>(`${base(profileId)}/enroll/confirm`, { code })
  return data
}

export async function regenerateTotpRecoveryCodes(profileId: string, code: string): Promise<TotpRecoveryResult> {
  const { data } = await apiClient.post<TotpRecoveryResult>(`${base(profileId)}/recovery-codes/regenerate`, { code })
  return data
}

export async function disableTotp(profileId: string, code: string): Promise<TotpStatus> {
  const { data } = await apiClient.post<{ status: TotpStatus }>(`${base(profileId)}/disable`, { code })
  return data.status
}
