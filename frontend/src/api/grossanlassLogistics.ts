import apiClient from './apiClient'

export type GaPlaceKind = 'bauprojekt' | 'unterlager' | 'matplatz' | 'anfahrt' | 'poi' | 'area'

export type GaPolygonPoint = { lat: number; lng: number }

export type GaPlace = {
  id: string
  name: string
  group_id: string | null
  unterlager_id: string | null
  kind?: GaPlaceKind
  map_id?: string | null
  map_x?: number | null
  map_y?: number | null
  latitude?: number | null
  longitude?: number | null
  polygon?: GaPolygonPoint[] | null
  starred?: boolean
  public_code: string
  qr_url: string
  can_delete?: boolean
  can_change_kind?: boolean
}

export type GaMapBounds = {
  north: number
  south: number
  east: number
  west: number
}

export type GaMap = {
  id: string
  name: string
  image_url: string | null
  image_width: number
  image_height: number
  overlay_opacity?: number
  bounds_north?: number | null
  bounds_south?: number | null
  bounds_east?: number | null
  bounds_west?: number | null
  places: GaPlace[]
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
  payload: {
    name: string
    group_id?: string | null
    kind?: GaPlaceKind
    map_id?: string | null
    map_x?: number | null
    map_y?: number | null
    latitude?: number | null
    longitude?: number | null
    polygon?: GaPolygonPoint[] | null
    starred?: boolean
  },
): Promise<GaPlace> {
  const { data } = await apiClient.post<GaPlace>(
    `/api/departments/${departmentId}/grossanlass/places`,
    payload,
  )
  return data
}

export async function updateGrossanlassPlace(
  departmentId: string,
  placeId: string,
  payload: {
    name?: string
    kind?: GaPlaceKind
    map_id?: string | null
    map_x?: number | null
    map_y?: number | null
    latitude?: number | null
    longitude?: number | null
    polygon?: GaPolygonPoint[] | null
    starred?: boolean
  },
): Promise<GaPlace> {
  const { data } = await apiClient.patch<GaPlace>(
    `/api/departments/${departmentId}/grossanlass/places/${placeId}`,
    payload,
  )
  return data
}

export async function deleteGrossanlassPlace(
  departmentId: string,
  placeId: string,
): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/places/${placeId}`)
}

export async function listGrossanlassMaps(departmentId: string): Promise<GaMap[]> {
  const { data } = await apiClient.get<GaMap[]>(
    `/api/departments/${departmentId}/grossanlass/maps`,
  )
  return data
}

export async function createGrossanlassMap(
  departmentId: string,
  payload: { name?: string } = {},
): Promise<GaMap> {
  const { data } = await apiClient.post<GaMap>(
    `/api/departments/${departmentId}/grossanlass/maps`,
    payload,
  )
  return data
}

export async function updateGrossanlassMap(
  departmentId: string,
  mapId: string,
  payload: Partial<GaMapBounds> & { name?: string; overlay_opacity?: number | null },
): Promise<GaMap> {
  const { data } = await apiClient.patch<GaMap>(
    `/api/departments/${departmentId}/grossanlass/maps/${mapId}`,
    {
      name: payload.name,
      bounds_north: payload.north,
      bounds_south: payload.south,
      bounds_east: payload.east,
      bounds_west: payload.west,
      overlay_opacity: payload.overlay_opacity,
    },
  )
  return data
}

export async function uploadGrossanlassMapBackground(
  departmentId: string,
  mapId: string,
  file: File,
  bounds?: GaMapBounds | null,
): Promise<GaMap> {
  const formData = new FormData()
  formData.append('file', file)
  if (bounds) {
    formData.append('bounds_north', String(bounds.north))
    formData.append('bounds_south', String(bounds.south))
    formData.append('bounds_east', String(bounds.east))
    formData.append('bounds_west', String(bounds.west))
  }
  const { data } = await apiClient.post<GaMap>(
    `/api/departments/${departmentId}/grossanlass/maps/${mapId}/background`,
    formData,
  )
  return data
}

export async function deleteGrossanlassMapBackground(
  departmentId: string,
  mapId: string,
): Promise<GaMap> {
  const { data } = await apiClient.delete<GaMap>(
    `/api/departments/${departmentId}/grossanlass/maps/${mapId}/background`,
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
