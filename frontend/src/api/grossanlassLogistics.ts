import apiClient from './apiClient'

export type GaPlaceKind = 'bauprojekt' | 'unterlager' | 'lagerplatz' | 'anfahrt' | 'poi' | 'area'

export type GaPolygonPoint = { lat: number; lng: number }

export type GaPlace = {
  id: string
  name: string
  /** Übergang: nur gesetzt, wenn der Standort genau einer Gruppe gehört; sonst null. Führend ist group_ids. */
  group_id: string | null
  /** Alle zugeordneten Gruppen (Ressort/Bereich/Bauprojekt); mehrere = gemeinsam genutzter Standort. */
  group_ids?: string[]
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

export type GaDispatchNeed = {
  /** Einsatz-ID bzw. `vn:<Fahrzeugwunsch-ID>` für Fahrzeugwünsche ohne Fahrauftrag. */
  id: string
  einsatz_id: string | null
  vehicle_need_id: string | null
  /** Fahrzeugwunsch, aus dem dieser Fahrauftrag überführt wurde (direkte Verknüpfung). */
  converted_from_vehicle_need_id?: string | null
  /** Nur bei Fahrzeugwünschen: `transport` ist in einen Fahrauftrag überführbar. */
  purpose?: 'site_use' | 'transport'
  source: 'packen' | 'beschaffung' | 'ressort' | 'planung'
  title: string
  from: string | null
  to: string
  starts_at: string
  ends_at: string
  ready: boolean
  /** Warntext des Packs, `noPack` oder null. */
  ready_note: string | null
  cargo: string
  target: string
  requirements: string[]
  scope: 'intern' | 'gelaende' | 'extern'
  status: 'open' | 'planned' | 'underway' | 'done'
  released: boolean
  einsatz_status: string | null
  chauffeur_user_id: string | null
  vehicle_id: string | null
  pack_ids: string[]
  from_place_id: string | null
  to_place_id: string | null
  tour_id: string | null
  tour_status: 'draft' | 'confirmed' | 'closed' | null
  /** Stand des Packs im QR-Ablauf: staging → trip_released → in_transit → at_place (`none` ohne Pack). */
  pack_status: 'none' | 'staging' | 'trip_released' | 'in_transit' | 'at_place'
}

export type GaDispatchStop = {
  id: string
  kind: 'load' | 'pickup' | 'unload'
  place_id: string | null
  label: string
  planned_at: string | null
  need_id: string | null
}

export type GaDispatchConflict = { kind: 'vehicle' | 'chauffeur'; with: string; from: string; to: string }

export type GaDispatchTour = {
  id: string
  name: string
  /** `draft` und `confirmed` sind gespeichert, `underway` und `done` ergeben sich aus den Fahraufträgen. */
  status: 'draft' | 'confirmed' | 'underway' | 'done' | 'closed'
  confirmed: boolean
  vehicle_id: string | null
  chauffeur_user_id: string | null
  trailer: string | null
  confirmed_at: string | null
  need_ids: string[]
  /** Nach dem Abschluss: `delivered` oder `not_executed` je Bedarf, sonst null. */
  outcomes: Array<{ need_id: string; outcome: 'delivered' | 'not_executed' | null }>
  closed_at: string | null
  stops: GaDispatchStop[]
  starts_at: string | null
  ends_at: string | null
  conflicts: GaDispatchConflict[]
  events: Array<{ action: string; note: string; at: string; user: string }>
}

export type GaDispatchSuggestion = {
  id: string
  reason: 'sameTarget' | 'sameWindow'
  need_ids: string[]
  day: string
  starts_at: string
  ends_at: string
}

export type GaDispatchChangeRequest = {
  id: string
  need_id: string
  title: string
  requested_by: string
  note: string
  proposed_starts_at: string | null
  proposed_ends_at: string | null
  proposed_destination_place_id: string | null
  status: 'open' | 'accepted' | 'adjusted' | 'rejected'
  decision_note: string
  decided_at: string | null
  created_at: string
}

export type GaDispatchOverview = {
  /** Geschäftszeit des Departments (BusinessClock), Wandzeit ohne Offset. */
  now: string
  /** Nur Logistikverantwortliche dürfen Touren ändern und bestätigen. */
  can_manage: boolean
  needs: GaDispatchNeed[]
  tours: GaDispatchTour[]
  suggestions: GaDispatchSuggestion[]
  change_requests: GaDispatchChangeRequest[]
  drivers: Array<{ id: string; name: string }>
  vehicles: Array<{ id: string; name: string; kind: string; trailer_ok: boolean; defect: boolean }>
}

export async function getGrossanlassDispatch(departmentId: string): Promise<GaDispatchOverview> {
  const { data } = await apiClient.get<GaDispatchOverview>(
    `/api/departments/${departmentId}/grossanlass/logistics/dispatch`,
  )
  return data
}

const dispatchBase = (departmentId: string) => `/api/departments/${departmentId}/grossanlass/logistics`

export type GaDispatchTourPayload = {
  name?: string
  vehicle_id?: string | null
  chauffeur_user_id?: string | null
  trailer?: string | null
}

export async function createGrossanlassDispatchTour(
  departmentId: string,
  payload: GaDispatchTourPayload & { need_ids: string[] },
): Promise<GaDispatchTour> {
  const { data } = await apiClient.post<GaDispatchTour>(`${dispatchBase(departmentId)}/tours`, payload)
  return data
}

export async function updateGrossanlassDispatchTour(departmentId: string, tourId: string, payload: GaDispatchTourPayload): Promise<GaDispatchTour> {
  const { data } = await apiClient.patch<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}`, payload)
  return data
}

export async function deleteGrossanlassDispatchTour(departmentId: string, tourId: string): Promise<void> {
  await apiClient.delete(`${dispatchBase(departmentId)}/tours/${tourId}`)
}

export async function addGrossanlassDispatchTourNeed(departmentId: string, tourId: string, needId: string): Promise<GaDispatchTour> {
  const { data } = await apiClient.post<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}/needs`, { need_id: needId })
  return data
}

export async function removeGrossanlassDispatchTourNeed(departmentId: string, tourId: string, needId: string): Promise<GaDispatchTour | { deleted: true }> {
  const { data } = await apiClient.delete<GaDispatchTour | { deleted: true }>(`${dispatchBase(departmentId)}/tours/${tourId}/needs/${encodeURIComponent(needId)}`)
  return data
}

export async function replaceGrossanlassDispatchTourStops(
  departmentId: string,
  tourId: string,
  stops: Array<Omit<GaDispatchStop, 'id'>>,
): Promise<GaDispatchTour> {
  const { data } = await apiClient.put<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}/stops`, { stops })
  return data
}

export async function confirmGrossanlassDispatchTour(departmentId: string, tourId: string): Promise<GaDispatchTour> {
  const { data } = await apiClient.post<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}/confirm`)
  return data
}

export async function unconfirmGrossanlassDispatchTour(departmentId: string, tourId: string): Promise<GaDispatchTour> {
  const { data } = await apiClient.post<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}/unconfirm`)
  return data
}

export async function releaseGrossanlassDispatchNeed(departmentId: string, needId: string): Promise<GaDispatchNeed> {
  const { data } = await apiClient.post<GaDispatchNeed>(`${dispatchBase(departmentId)}/needs/${encodeURIComponent(needId)}/release`)
  return data
}

/** Überführt einen Fahrzeugwunsch (`vn:<id>`) in einen nicht freigegebenen Fahrauftrag. */
export async function convertGrossanlassDispatchVehicleNeed(departmentId: string, needId: string): Promise<GaDispatchNeed> {
  const { data } = await apiClient.post<GaDispatchNeed>(`${dispatchBase(departmentId)}/needs/${encodeURIComponent(needId)}/convert`)
  return data
}

export async function revokeGrossanlassDispatchRelease(departmentId: string, needId: string): Promise<GaDispatchNeed> {
  const { data } = await apiClient.post<GaDispatchNeed>(`${dispatchBase(departmentId)}/needs/${encodeURIComponent(needId)}/revoke-release`)
  return data
}

export async function releaseGrossanlassDispatchTour(departmentId: string, tourId: string): Promise<GaDispatchTour> {
  const { data } = await apiClient.post<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}/release`)
  return data
}

export async function closeGrossanlassDispatchTour(departmentId: string, tourId: string, note = ''): Promise<GaDispatchTour> {
  const { data } = await apiClient.post<GaDispatchTour>(`${dispatchBase(departmentId)}/tours/${tourId}/close`, { note })
  return data
}

/** Ablieferung ohne lesbaren Ziel-QR: nur Chauffeur des Fahrauftrags oder Logistik, mit Begründung (serverseitig protokolliert). */
export async function arriveGrossanlassPackManually(departmentId: string, packId: string, reason: string): Promise<GaLogisticsPack> {
  const { data } = await apiClient.post<GaLogisticsPack>(`/api/departments/${departmentId}/grossanlass/packs/${packId}/arrive-manual`, { reason })
  return data
}

export type GaChangeRequestPayload = {
  note?: string
  starts_at?: string | null
  ends_at?: string | null
  destination_place_id?: string | null
}

export async function requestGrossanlassTripChange(departmentId: string, needId: string, payload: GaChangeRequestPayload): Promise<GaDispatchChangeRequest> {
  const { data } = await apiClient.post<GaDispatchChangeRequest>(`${dispatchBase(departmentId)}/needs/${encodeURIComponent(needId)}/change-requests`, payload)
  return data
}

export async function listGrossanlassChangeRequests(departmentId: string): Promise<GaDispatchChangeRequest[]> {
  const { data } = await apiClient.get<GaDispatchChangeRequest[]>(`${dispatchBase(departmentId)}/change-requests`)
  return data
}

export async function decideGrossanlassChangeRequest(
  departmentId: string,
  requestId: string,
  payload: { decision: 'accept' | 'adjust' | 'reject'; note?: string } & GaChangeRequestPayload,
): Promise<GaDispatchChangeRequest> {
  const { data } = await apiClient.post<GaDispatchChangeRequest>(`${dispatchBase(departmentId)}/change-requests/${requestId}/decision`, payload)
  return data
}
