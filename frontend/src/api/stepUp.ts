import apiClient from './apiClient'

export type StepUpResult = { step_up: true; valid_for: number }

/** Frische MFA-Bestätigung der aktuellen Sitzung (TOTP- oder Recovery Code). Keine neuen Tokens. */
export async function confirmStepUp(code: string): Promise<StepUpResult> {
  const { data } = await apiClient.post<StepUpResult>('/api/auth/step-up', { code })
  return data
}
