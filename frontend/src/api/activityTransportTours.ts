import apiClient from './apiClient'

export type TransportTourDirection = 'outbound' | 'inbound'

export type TransportTourLoadFit = 'ok' | 'heavy' | 'unknown'

export interface TransportTourLoadSummary {
  estimated_weight_kg: number
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
}

export interface ActivityTransportTour {
  id: string
  activity_id: string
  label: string
  direction: TransportTourDirection
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
}

function mapTour(raw: Record<string, unknown>): ActivityTransportTour {
  const load = (raw.load_summary ?? {}) as Record<string, unknown>
  const itemsRaw = Array.isArray(raw.items) ? raw.items : []
  return {
    id: String(raw.id ?? ''),
    activity_id: String(raw.activity_id ?? ''),
    label: String(raw.label ?? ''),
    direction: (raw.direction === 'inbound' ? 'inbound' : 'outbound') as TransportTourDirection,
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
      }
    }),
    load_summary: {
      estimated_weight_kg: Number(load.estimated_weight_kg ?? 0),
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
  body: { label?: string; notes?: string; items?: TransportTourItemInput[] },
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

export function directionForJourneyStep(step: string): TransportTourDirection | null {
  if (step === 'transport_out') return 'outbound'
  if (step === 'transport_back') return 'inbound'
  return null
}
