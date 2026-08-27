import apiClient from './apiClient'
import type { GrossanlassUserCard } from './grossanlassUserCards'

export type GaUebersichtEinsatz = {
  id: string
  kind: 'einsatz' | 'order'
  object_id: string
  object_name: string
  einsatz_kind: 'unique' | 'quantity'
  qty: number
  stock: number
  from: string
  to: string
  ressort: string
  group_id: string | null
  status: 'planned' | 'pending_approval' | 'issued' | 'returned'
  who: string
  place: 'lager' | 'assigned' | 'out'
  packed: boolean
  wish_line_id: string | null
  chauffeur_user_id: string | null
  issued_to_user_id: string | null
  bar_role: 'einsatz'
  conflict_id?: string
}

export type GaUebersichtConflict = {
  id: string
  kind: 'unique_overlap' | 'quantity_overbook'
  object_id: string
  object_name: string
  einsatz_ids: string[]
  title: string
  text: string
}

export type GaUebersichtIssue = {
  id: string
  name: string
  qty: number
  family: 'vehicle' | 'material'
  place: 'lager' | 'assigned' | 'out'
  recipient_kind: 'ressort' | 'guest'
  recipient: string
  driver_ok: boolean
  bucket: 'today' | 'tomorrow' | 'express'
  when_label: string
  planned_for: string
  person_id: string | null
  status: string
}

export type GaUebersichtPack = {
  id: string
  phase: 'aufbau' | 'anlass'
  name: string
  qty: number
  packed: boolean
}

export type GaUebersichtReturn = {
  id: string
  name: string
  firm: string
  due: string
  returned: boolean
}

export type GaUebersichtWish = {
  id: string
  label: string
  object_id: string
  object_name: string
  kind: 'unique' | 'quantity'
  qty: number
  stock: number
  from: string
  to: string
  ressort: string
  group_id: string
  who: string
}

export type GaUebersichtPayload = {
  einsaetze: GaUebersichtEinsatz[]
  orders: GaUebersichtEinsatz[]
  conflicts: GaUebersichtConflict[]
  issues: GaUebersichtIssue[]
  pack: GaUebersichtPack[]
  returns: GaUebersichtReturn[]
  cards: GrossanlassUserCard[]
  wishes: GaUebersichtWish[]
  issued_by_object: Record<string, number>
}

export type GaUebersichtCreatePayload = {
  kind?: 'einsatz' | 'order'
  commitment_id?: string
  object_id?: string
  wish_line_id?: string | null
  group_id?: string | null
  qty?: number
  from: string
  to: string
  who?: string
  chauffeur_user_id?: string | null
  pending?: boolean
  has_conflict?: boolean
}

export async function getGrossanlassUebersicht(departmentId: string): Promise<GaUebersichtPayload> {
  const response = await apiClient.get<GaUebersichtPayload>(
    `/api/departments/${departmentId}/grossanlass/uebersicht`,
  )
  return response.data
}

export async function createGrossanlassEinsatz(
  departmentId: string,
  data: GaUebersichtCreatePayload,
): Promise<GaUebersichtPayload> {
  const response = await apiClient.post<GaUebersichtPayload>(
    `/api/departments/${departmentId}/grossanlass/uebersicht/einsaetze`,
    data,
  )
  return response.data
}

export async function updateGrossanlassEinsatz(
  departmentId: string,
  id: string,
  data: { packed?: boolean; status?: string; pack_phase?: string },
): Promise<GaUebersichtPayload> {
  const response = await apiClient.patch<GaUebersichtPayload>(
    `/api/departments/${departmentId}/grossanlass/uebersicht/einsaetze/${id}`,
    data,
  )
  return response.data
}

export async function issueGrossanlassEinsatz(
  departmentId: string,
  id: string,
  data: { user_id?: string },
): Promise<GaUebersichtPayload> {
  const response = await apiClient.post<GaUebersichtPayload>(
    `/api/departments/${departmentId}/grossanlass/uebersicht/einsaetze/${id}/issue`,
    data,
  )
  return response.data
}

export async function updateGrossanlassUebersichtCommitment(
  departmentId: string,
  commitmentId: string,
  data: { packed?: boolean; pack_phase?: string; returned_to_firm?: boolean },
): Promise<GaUebersichtPayload> {
  const response = await apiClient.patch<GaUebersichtPayload>(
    `/api/departments/${departmentId}/grossanlass/uebersicht/commitments/${commitmentId}`,
    data,
  )
  return response.data
}
