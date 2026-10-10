import apiClient from './apiClient'

export interface DisplayPairingStart {
  request_id: string
  pair_url: string
  user_code: string
  poll_secret: string
  expires_at: string
  poll_interval_seconds: number
}

export type DisplayPairingPoll =
  | { status: 'pending' | 'expired' | 'revoked' }
  | { status: 'approved'; device_name?: string }

export interface PairableDisplayScreen {
  id: string
  name: string
  department_id: string
  department_name: string
  is_grossanlass: boolean
}

export interface DisplayPairingRequestInfo {
  user_code: string
  expires_at: string
}

/** Fernseher: neue Kopplungsanfrage starten (öffentlich, ohne Login). */
export async function startDisplayPairing(): Promise<DisplayPairingStart> {
  const res = await apiClient.post<DisplayPairingStart>('/api/public/display-pairing', {}, { withCredentials: true })
  return res.data
}

/** Fernseher: Status abfragen; bei Freigabe setzt die Antwort das Display-Cookie. */
export async function pollDisplayPairing(requestId: string, pollSecret: string): Promise<DisplayPairingPoll> {
  const res = await apiClient.post<DisplayPairingPoll>(
    `/api/public/display-pairing/${encodeURIComponent(requestId)}/poll`,
    { poll_secret: pollSecret },
    { withCredentials: true },
  )
  return res.data
}

/** Smartphone (angemeldet): Infoscreens, die der User koppeln darf. */
export async function listPairableDisplayScreens(): Promise<PairableDisplayScreen[]> {
  const res = await apiClient.get<PairableDisplayScreen[]>('/api/display-pairing/screens')
  return res.data || []
}

export async function getDisplayPairingRequest(token: string): Promise<DisplayPairingRequestInfo> {
  const res = await apiClient.get<DisplayPairingRequestInfo>(
    `/api/display-pairing/requests/${encodeURIComponent(token)}`,
  )
  return res.data
}

export async function approveDisplayPairing(token: string, screenId: string, deviceName: string): Promise<void> {
  await apiClient.post(`/api/display-pairing/requests/${encodeURIComponent(token)}/approve`, {
    screen_id: screenId,
    device_name: deviceName,
  })
}
