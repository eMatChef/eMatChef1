import apiClient from './apiClient'

export type GaPlace = {
  id: string
  name: string
  group_id: string | null
  unterlager_id: string | null
  public_code: string
  qr_url: string
}

export type GaPackLine = {
  id: string
  label: string
  commitment_id: string | null
  wish_line_id: string | null
  qty_needed: number
  qty_packed: number
  valid_from: string | null
  valid_to: string | null
  incomplete: boolean
}

export type GaLogisticsPack = {
  id: string
  einsatz_id: string
  public_code: string
  qr_url: string
  status: 'staging' | 'trip_released' | 'in_transit' | 'at_place'
  trip_released: boolean
  trip_released_at: string | null
  current_place_id: string | null
  current_place_name: string | null
  sort_order: number
  incomplete: boolean
  warning: string | null
  lines: GaPackLine[]
  department?: { id: string; name: string }
  entity_type?: string
}

export async function listGrossanlassPlaces(departmentId: string): Promise<GaPlace[]> {
  const { data } = await apiClient.get<GaPlace[]>(
    `/api/departments/${departmentId}/grossanlass/places`,
  )
  return data
}

export async function createGrossanlassPlace(
  departmentId: string,
  payload: { name: string; group_id?: string | null },
): Promise<GaPlace> {
  const { data } = await apiClient.post<GaPlace>(
    `/api/departments/${departmentId}/grossanlass/places`,
    payload,
  )
  return data
}

export async function listGrossanlassPacks(
  departmentId: string,
  einsatzId: string,
): Promise<GaLogisticsPack[]> {
  const { data } = await apiClient.get<GaLogisticsPack[]>(
    `/api/departments/${departmentId}/grossanlass/einsaetze/${einsatzId}/packs`,
  )
  return data
}

export async function addGrossanlassPack(
  departmentId: string,
  einsatzId: string,
): Promise<GaLogisticsPack> {
  const { data } = await apiClient.post<GaLogisticsPack>(
    `/api/departments/${departmentId}/grossanlass/einsaetze/${einsatzId}/packs`,
  )
  return data
}

export async function updateGrossanlassPackLine(
  departmentId: string,
  lineId: string,
  payload: { qty_packed?: number; qty_needed?: number; valid_from?: string | null; valid_to?: string | null },
): Promise<GaLogisticsPack> {
  const { data } = await apiClient.patch<GaLogisticsPack>(
    `/api/departments/${departmentId}/grossanlass/pack-lines/${lineId}`,
    payload,
  )
  return data
}

export async function releaseGrossanlassPack(
  departmentId: string,
  packId: string,
): Promise<GaLogisticsPack> {
  const { data } = await apiClient.post<GaLogisticsPack>(
    `/api/departments/${departmentId}/grossanlass/packs/${packId}/release`,
  )
  return data
}

export async function scanStartGrossanlassPack(
  departmentId: string,
  packId: string,
): Promise<GaLogisticsPack> {
  const { data } = await apiClient.post<GaLogisticsPack>(
    `/api/departments/${departmentId}/grossanlass/packs/${packId}/scan-start`,
  )
  return data
}

export async function scanArriveGrossanlassPack(
  departmentId: string,
  packId: string,
  placeId: string,
): Promise<GaLogisticsPack> {
  const { data } = await apiClient.post<GaLogisticsPack>(
    `/api/departments/${departmentId}/grossanlass/packs/${packId}/scan-arrive`,
    { place_id: placeId },
  )
  return data
}

const ACTIVE_PACK_KEY = 'ematchef.ga.activePack'

export type GaActivePack = {
  packId: string
  departmentId: string
  publicCode: string
}

export function rememberActivePack(pack: GaActivePack): void {
  sessionStorage.setItem(ACTIVE_PACK_KEY, JSON.stringify(pack))
}

export function readActivePack(): GaActivePack | null {
  try {
    const raw = sessionStorage.getItem(ACTIVE_PACK_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as GaActivePack
    if (!parsed.packId || !parsed.departmentId) return null
    return parsed
  } catch {
    return null
  }
}

export function clearActivePack(): void {
  sessionStorage.removeItem(ACTIVE_PACK_KEY)
}
