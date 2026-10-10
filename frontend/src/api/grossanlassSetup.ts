import apiClient from './apiClient'

export type GaSetupStepId = 'stammdaten' | 'ressorts' | 'mitglieder'

export type GaSetupMissing = {
  code: string
  /** Ressortname bei `ressort_without_leader`. */
  name?: string
}

export type GaSetupStep = {
  id: GaSetupStepId
  done: boolean
  missing: GaSetupMissing[]
}

/** Stand der Ersteinrichtung eines Grossanlasses (drei Pflichtbereiche und Freigabe). */
export type GaSetupStatus = {
  released: boolean
  released_at: string | null
  /** MW, Co-MW, OK-Leitung (oder Admin im Scope): einrichten. */
  can_setup: boolean
  /** Nur MW und OK-Leitung (oder Admin im Scope): freigeben. */
  can_release: boolean
  complete: boolean
  steps: GaSetupStep[]
}

export async function getGrossanlassSetup(departmentId: string): Promise<GaSetupStatus> {
  const response = await apiClient.get<GaSetupStatus>(`/api/departments/${departmentId}/grossanlass/setup`)
  return response.data
}

/** Gibt die Einrichtung frei. Unvollständig: HTTP 422 mit `steps`; ohne Berechtigung: HTTP 403. */
export async function releaseGrossanlassSetup(departmentId: string): Promise<GaSetupStatus> {
  const response = await apiClient.post<GaSetupStatus>(`/api/departments/${departmentId}/grossanlass/setup/release`)
  return response.data
}

/** Antwort der serverseitigen Sperre (HTTP 403, `code: grossanlass_setup_pending`). */
export function isGrossanlassSetupPending(err: unknown): boolean {
  const response = (err as { response?: { status?: number; data?: { code?: unknown } } })?.response
  return response?.status === 403 && response.data?.code === 'grossanlass_setup_pending'
}
