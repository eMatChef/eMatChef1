import apiClient from './apiClient'

export type TransportTourDirection = 'outbound' | 'inbound'

export type TransportTourStatus = 'planned' | 'in_transit' | 'arrived'

export type TransportTourLoadFit = 'ok' | 'heavy' | 'unknown'

export interface TransportTourLoadSummary {
  known_weight_kg: number
  unknown_weight_count: number
  estimated_volume_m3: number | null
  max_payload_kg: number | null
  max_volume_m3: number | null
  fit: TransportTourLoadFit
}

export interface ActivityTransportTourItem {
  id: string
  pack_container_id: string | null
  pack_item_id: string | null
  quantity: number | null
  measured_weight_kg: number | null
  measured_weight_inherited: boolean
  material_weight_known: boolean
  material_item_id: string | null
}

export interface ActivityTransportTour {
  id: string
  activity_id: string
  label: string
  direction: TransportTourDirection
  status: TransportTourStatus
  sort_order: number
  notes: string | null
  vehicle_id: string
  vehicle_name: string
  vehicle_plate: string | null
  lending_department_id: string | null
  items: ActivityTransportTourItem[]
  load_summary: TransportTourLoadSummary
}

export type TransportTourItemInput = {
  pack_container_id?: string
  pack_item_id?: string
  quantity?: number
  measured_weight_kg?: number | null
}

export type TourDisplayLabelSource = Pick<ActivityTransportTour, 'label' | 'vehicle_name'>

export function tourLabelIncludesVehicle(tour: TourDisplayLabelSource): boolean {
  const vehicle = tour.vehicle_name.trim()
  return vehicle.length > 0 && tour.label.includes(vehicle)
}

/** e.g. «Tour A Anhänger Bunzi» — vehicle omitted when already part of label. */
export function formatTourDisplayLabel(tour: TourDisplayLabelSource): string {
  const label = tour.label.trim()
  const vehicle = tour.vehicle_name.trim()
  if (!vehicle || tourLabelIncludesVehicle(tour)) return label
  return `${label} ${vehicle}`
}

export function mapTourItemsForPatch(
  items: ActivityTransportTourItem[],
): TransportTourItemInput[] {
  return items.map((item) => ({
    pack_container_id: item.pack_container_id ?? undefined,
    pack_item_id: item.pack_item_id ?? undefined,
    quantity: item.quantity ?? 1,
    measured_weight_kg: item.measured_weight_kg ?? undefined,
  }))
}

function mapTourStatus(raw: unknown): TransportTourStatus {
  if (raw === 'in_transit' || raw === 'arrived') return raw
  return 'planned'
}

function mapTour(raw: Record<string, unknown>): ActivityTransportTour {
  const load = (raw.load_summary ?? {}) as Record<string, unknown>
  const itemsRaw = Array.isArray(raw.items) ? raw.items : []
  return {
    id: String(raw.id ?? ''),
    activity_id: String(raw.activity_id ?? ''),
    label: String(raw.label ?? ''),
    direction: (raw.direction === 'inbound' ? 'inbound' : 'outbound') as TransportTourDirection,
    status: mapTourStatus(raw.status),
    sort_order: Number(raw.sort_order ?? 0),
    notes: raw.notes != null ? String(raw.notes) : null,
    vehicle_id: String(raw.vehicle_id ?? ''),
    vehicle_name: String(raw.vehicle_name ?? ''),
    vehicle_plate: raw.vehicle_plate != null ? String(raw.vehicle_plate) : null,
    lending_department_id:
      raw.lending_department_id != null ? String(raw.lending_department_id) : null,
    items: itemsRaw.map((row) => {
      const r = row as Record<string, unknown>
      return {
        id: String(r.id ?? ''),
        pack_container_id: r.pack_container_id != null ? String(r.pack_container_id) : null,
        pack_item_id: r.pack_item_id != null ? String(r.pack_item_id) : null,
        quantity: r.quantity != null ? Number(r.quantity) : null,
        measured_weight_kg:
          r.measured_weight_kg != null ? Number(r.measured_weight_kg) : null,
        measured_weight_inherited: Boolean(r.measured_weight_inherited),
        material_weight_known: Boolean(r.material_weight_known),
        material_item_id:
          r.material_item_id != null ? String(r.material_item_id) : null,
      }
    }),
    load_summary: {
      known_weight_kg: Number(
        load.known_weight_kg ?? load.estimated_weight_kg ?? 0,
      ),
      unknown_weight_count: Number(load.unknown_weight_count ?? 0),
      estimated_volume_m3:
        load.estimated_volume_m3 != null ? Number(load.estimated_volume_m3) : null,
      max_payload_kg: load.max_payload_kg != null ? Number(load.max_payload_kg) : null,
      max_volume_m3: load.max_volume_m3 != null ? Number(load.max_volume_m3) : null,
      fit: (['ok', 'heavy'].includes(String(load.fit))
        ? load.fit
        : 'unknown') as TransportTourLoadFit,
    },
  }
}

export async function getActivityTransportTours(
  activityId: string,
  direction: TransportTourDirection,
): Promise<ActivityTransportTour[]> {
  const { data } = await apiClient.get<Record<string, unknown>[]>(
    `/api/activities/${activityId}/transport-tours?direction=${direction}`,
  )
  return (Array.isArray(data) ? data : []).map((row) => mapTour(row))
}

export async function createActivityTransportTour(
  activityId: string,
  body: { vehicle_id: string; direction: TransportTourDirection; label?: string },
): Promise<ActivityTransportTour> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/transport-tours`,
    body,
  )
  return mapTour(data ?? {})
}

export async function updateActivityTransportTour(
  activityId: string,
  tourId: string,
  body: {
    label?: string
    notes?: string
    status?: TransportTourStatus
    items?: TransportTourItemInput[]
  },
): Promise<ActivityTransportTour> {
  const { data } = await apiClient.patch<Record<string, unknown>>(
    `/api/activities/${activityId}/transport-tours/${tourId}`,
    body,
  )
  return mapTour(data ?? {})
}

export async function deleteActivityTransportTour(
  activityId: string,
  tourId: string,
): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/transport-tours/${tourId}`)
}

export async function arriveActivityTransportTour(
  activityId: string,
  tourId: string,
): Promise<ActivityTransportTour> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/transport-tours/${tourId}/arrive`,
  )
  const tour = (data?.tour ?? data) as Record<string, unknown>
  return mapTour(tour ?? {})
}

export async function arriveAllActivityTransportTours(
  activityId: string,
  direction: TransportTourDirection,
): Promise<{ applied_units: number; updated_lines: number; tours_marked: number }> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/transport-tours/arrive-all`,
    { direction },
  )
  return {
    applied_units: Number(data?.applied_units ?? 0),
    updated_lines: Number(data?.updated_lines ?? 0),
    tours_marked: Number(data?.tours_marked ?? 0),
  }
}

/** Touren-Planung (Zuordnung, Abfahrt) vs. Ankunft buchen. */
export function transportTourUiModeForJourneyStep(
  step: string,
): 'plan' | 'arrival' | null {
  if (step === 'transport_out' || step === 'transport_back') return 'plan'
  if (step === 'issue') return 'arrival'
  return null
}

export function directionForJourneyStep(step: string): TransportTourDirection | null {
  if (step === 'transport_out' || step === 'issue') return 'outbound'
  if (step === 'transport_back') return 'inbound'
  return null
}
