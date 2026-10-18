import apiClient from './apiClient'
import type { GrossanlassCommitment } from './grossanlassCommitments'

export type GaInboundShipmentGroup = 'tour' | 'trip' | 'direct' | 'unassigned'
export type GaInboundPhase = 'expected' | 'acceptance' | 'received'

export type GaInboundPosition = {
  commitment_id: string
  name: string
  supplier: string
  origin: string
  expected_qty: number
  received_qty: number
  open_qty: number
  inbound_state: string
  inbound_mode: 'pickup' | 'delivery'
  arrives_at: string | null
  order_ref: string
  procurement_line_id: string | null
  barcode: string | null
  need_id?: string
  transport_status?: string | null
  from?: string | null
  to?: string | null
  target_place_id?: string | null
}

export type GaInboundShipment = {
  id: string
  group: GaInboundShipmentGroup
  title: string
  phase: GaInboundPhase
  position_count: number
  progress: { expected: number; received: number; open: number }
  arrives_at: string | null
  positions: GaInboundPosition[]
  tour_id?: string
  tour_status?: string
  chauffeur_user_id?: string | null
  vehicle_id?: string | null
  target_place_id?: string | null
  transport_status?: string | null
  need_id?: string
  from?: string | null
  to?: string | null
  stops?: Array<{ id: string; kind: string; place_id: string | null; label: string; planned_at: string | null; need_id: string | null }>
}

export type GaInboundWorkspace = {
  storage_places: Array<{ id: string; name: string }>
  drivers: Record<string, string>
  vehicles: Record<string, string>
  phases: Record<GaInboundPhase, GaInboundShipment[]>
  shipments: GaInboundShipment[]
}

export type GaInboundReceiveLine = {
  commitment_id: string
  quantity: number
  note?: string | null
  skip?: boolean
  place_id?: string | null
}

export async function getGrossanlassInboundWorkspace(departmentId: string): Promise<GaInboundWorkspace> {
  const { data } = await apiClient.get<GaInboundWorkspace>(
    `/api/departments/${departmentId}/grossanlass/inbound/workspace`,
  )
  return data
}

export async function receiveGrossanlassInboundBatch(
  departmentId: string,
  payload: { place_id?: string | null; lines: GaInboundReceiveLine[] },
): Promise<{ results: Array<{ movement: { id: string; commitment_id: string }; inbound: unknown; commitment: GrossanlassCommitment }>; place_id: string }> {
  const { data } = await apiClient.post(
    `/api/departments/${departmentId}/grossanlass/inbound/receive`,
    payload,
  )
  return data
}
