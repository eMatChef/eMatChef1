import apiClient from './apiClient'
import type { DisplayMapData } from './displayLayout'
import type { GaLiveData } from './displayLayout'
import { mapDisplayPayload, type PublicDisplayData, type PublicDisplayDataResponse } from './displayScreens'

/** Serverseitiger Zustand des Anzeigegeräts (Credential-Cookie, keine User-Anmeldung). */
export type DisplayDeviceState = 'none' | 'revoked' | 'expired' | 'active'

export interface DisplayDeviceInfo {
  id: string
  name: string
  approval_expires_at: string
}

export interface DisplayDeviceSession {
  state: DisplayDeviceState
  device?: DisplayDeviceInfo
}

export interface DisplayDeviceData extends DisplayDeviceSession {
  data?: PublicDisplayData
}

// 401 (kein/ungültiges/widerrufenes Gerät) und 403 (Freigabe abgelaufen) sind normale Zustände, keine Fehler.
const STATE_STATUSES = new Set([200, 401, 403])
const config = { withCredentials: true, validateStatus: (s: number) => STATE_STATUSES.has(s) }

export async function getDisplayDeviceSession(): Promise<DisplayDeviceSession> {
  const res = await apiClient.get<DisplayDeviceSession>('/api/public/display-device/session', config)
  return { state: res.data.state, device: res.data.device }
}

/** Wirft bei Netzwerk-/Serverfehlern (Offline-Betrieb), liefert sonst Zustand und bei Freigabe die Daten. */
export async function getDisplayDeviceData(): Promise<DisplayDeviceData> {
  const res = await apiClient.get<DisplayDeviceSession & PublicDisplayDataResponse>('/api/public/display-device/data', config)
  if (res.data.state !== 'active') return { state: res.data.state, device: res.data.device }
  return { state: 'active', device: res.data.device, data: mapDisplayPayload(res.data) }
}

/** Bildschirmprofil eines Geräts: Format, native Auflösung, Betrachtungsabstand, Lesbarkeit, Layoutvariante. */
export interface DisplayDeviceProfile {
  width_cm: number | null
  height_cm: number | null
  pixel_width: number | null
  pixel_height: number | null
  aspect_ratio: string | null
  viewing_distance_m: number | null
  font_profile: 'auto' | 'small' | 'normal' | 'large' | 'xlarge'
  /** null = automatische Auswahl nach Seitenverhältnis */
  variant_id: string | null
}

export interface DisplayDeviceRow {
  id: string
  screen_id: string
  name: string
  approval_state: 'active' | 'expired' | 'revoked'
  approval_expires_at: string
  approved_at: string
  online: boolean
  last_contact_at: string | null
  revoked_at: string | null
  created_via: 'pairing' | 'manual' | 'migrated'
  profile: DisplayDeviceProfile | null
  created_at: string
}

const base = (departmentId: string) => `/api/departments/${encodeURIComponent(departmentId)}/display-devices`

export async function listDisplayDevices(departmentId: string): Promise<DisplayDeviceRow[]> {
  const res = await apiClient.get<DisplayDeviceRow[]>(base(departmentId))
  return res.data || []
}

export async function updateDisplayDevice(
  departmentId: string,
  deviceId: string,
  body: { name?: string; screen_id?: string; profile?: Partial<DisplayDeviceProfile> | null },
): Promise<DisplayDeviceRow> {
  const res = await apiClient.patch<DisplayDeviceRow>(`${base(departmentId)}/${encodeURIComponent(deviceId)}`, body)
  return res.data
}

/** Freigabe auf 90 Tage ab jetzt setzen (auch Wiederfreigabe nach Ablauf). */
export async function extendDisplayDevice(departmentId: string, deviceId: string): Promise<DisplayDeviceRow> {
  const res = await apiClient.post<DisplayDeviceRow>(`${base(departmentId)}/${encodeURIComponent(deviceId)}/extend`)
  return res.data
}

export async function revokeDisplayDevice(departmentId: string, deviceId: string): Promise<DisplayDeviceRow> {
  const res = await apiClient.post<DisplayDeviceRow>(`${base(departmentId)}/${encodeURIComponent(deviceId)}/revoke`)
  return res.data
}

export async function deleteDisplayDevice(departmentId: string, deviceId: string): Promise<void> {
  await apiClient.delete(`${base(departmentId)}/${encodeURIComponent(deviceId)}`)
}

/** Kartendaten des zugewiesenen Infoscreens (Geräte-Credential); null, wenn das Gerät nicht (mehr) freigegeben ist. */
export async function getDisplayDeviceMapData(): Promise<DisplayMapData | null> {
  const res = await apiClient.get<DisplayMapData>('/api/public/display-device/map-data', config)
  return res.status === 200 ? res.data : null
}

/** Live-Daten der GA-Fachwidgets des zugewiesenen Infoscreens (Geräte-Credential, nur lesend). */
export async function getDisplayDeviceLiveData(): Promise<GaLiveData | null> {
  const res = await apiClient.get<GaLiveData>('/api/public/display-device/live-data', config)
  return res.status === 200 ? res.data : null
}
