import apiClient from './apiClient'
import type { GroupMember } from './groups'
import type { GaPlace, GaPolygonPoint } from './grossanlassLogistics'

export type GrossanlassGroupKind = 'ressort' | 'bereich' | 'teilbereich' | 'auftrag'
export type GrossanlassNodeType = 'ressort' | 'unterressort' | 'bauprojekt' | 'auftrag'

export type GrossanlassGroupShare = {
  id: string
  target_group_id?: string
  target_name?: string
  group_id?: string
  group_name?: string
}

export interface GrossanlassGroup {
  id: string
  name: string
  department_id: string
  parent_id: string | null
  sort_order: number
  level: number
  kind: GrossanlassGroupKind
  node_type: GrossanlassNodeType
  window_start?: string | null
  window_end?: string | null
  build_status?: string | null
  procurement_progress?: 'quoted' | 'build' | 'use' | null
  description?: string | null
  /** Hauptstandort (Polygon, sonst ältester Punkt). */
  place?: GaPlace | null
  /** Alle Standorte der Gruppe, Hauptstandort zuerst. */
  places?: GaPlace[]
  include_on_map?: boolean
  member_count: number
  leader_count: number
  members: GroupMember[]
  leaders: GroupMember[]
  shared_with?: GrossanlassGroupShare[]
  shared_from?: GrossanlassGroupShare[]
  created_at: string
  updated_at: string
}

export async function getGrossanlassGroups(departmentId: string): Promise<GrossanlassGroup[]> {
  const response = await apiClient.get<GrossanlassGroup[]>(
    `/api/departments/${departmentId}/grossanlass/groups`,
  )
  return response.data
}

/** Echte Kennzahlen einer Auftragskarte (Backend berechnet, nichts geschätzt). */
export type GrossanlassOrderCard = {
  group_id: string
  material: { items: number; covered_items: number; at_place_items: number }
  tasks: { total: number; scheduled: number; done: number }
  /** need: erfasster Helferbedarf (null = nicht erfasst); assigned: eingeteilte Personen */
  staffing: { need: number | null; assigned: number }
}

export async function getGrossanlassOrderCards(departmentId: string): Promise<GrossanlassOrderCard[]> {
  const response = await apiClient.get<GrossanlassOrderCard[]>(
    `/api/departments/${departmentId}/grossanlass/groups/order-cards`,
  )
  return response.data
}

export async function createGrossanlassGroup(
  departmentId: string,
  data: {
    name: string
    parent_id?: string | null
    kind?: GrossanlassGroupKind
    sort_order?: number
    window_start?: string | null
    window_end?: string | null
    build_status?: string | null
    description?: string | null
    include_on_map?: boolean
    polygon?: GaPolygonPoint[] | null
    /** Vorhandenen, freien Ort verwenden statt einen neuen anzulegen. */
    place_id?: string | null
    /** 'point' (Marker) oder 'area' (Polygon). */
    place_kind?: 'point' | 'area'
    /** Eigener Standortname; nur senden, wenn er bewusst gesetzt/geändert wurde. */
    place_name?: string
  },
): Promise<GrossanlassGroup> {
  const response = await apiClient.post<GrossanlassGroup>(
    `/api/departments/${departmentId}/grossanlass/groups`,
    data,
  )
  return response.data
}

export async function updateGrossanlassGroup(
  departmentId: string,
  groupId: string,
  data: {
    name?: string
    parent_id?: string | null
    kind?: GrossanlassGroupKind
    sort_order?: number
    window_start?: string | null
    window_end?: string | null
    build_status?: string | null
    description?: string | null
    include_on_map?: boolean
    polygon?: GaPolygonPoint[] | null
    /** Vorhandenen, freien Ort verwenden statt einen neuen anzulegen. */
    place_id?: string | null
    /** 'point' (Marker) oder 'area' (Polygon). */
    place_kind?: 'point' | 'area'
    /** Eigener Standortname; nur senden, wenn er bewusst gesetzt/geändert wurde. */
    place_name?: string
  },
): Promise<GrossanlassGroup> {
  const response = await apiClient.put<GrossanlassGroup>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassGroup(departmentId: string, groupId: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/groups/${groupId}`)
}

export async function shareGrossanlassGroup(
  departmentId: string,
  groupId: string,
  targetGroupId: string,
): Promise<GrossanlassGroupShare> {
  const { data } = await apiClient.post<GrossanlassGroupShare>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/shares`,
    { target_group_id: targetGroupId },
  )
  return data
}

export async function unshareGrossanlassGroup(
  departmentId: string,
  groupId: string,
  shareId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/shares/${shareId}`,
  )
}

export async function addGrossanlassGroupMember(
  departmentId: string,
  groupId: string,
  data: {
    user_id: string
    role?: string
    is_primary?: boolean
    can_procure?: boolean
  },
): Promise<GroupMember> {
  const response = await apiClient.post<GroupMember>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/members`,
    data,
  )
  return response.data
}

export async function updateGrossanlassGroupMember(
  departmentId: string,
  groupId: string,
  userId: string,
  data: {
    role?: string
    is_primary?: boolean
    can_procure?: boolean
  },
): Promise<GroupMember> {
  const response = await apiClient.patch<GroupMember>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/members/${userId}`,
    data,
  )
  return response.data
}

export async function removeGrossanlassGroupMember(
  departmentId: string,
  groupId: string,
  userId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/members/${userId}`,
  )
}

export type GrossanlassHelperResult = {
  created_user: boolean
  added_to_department: boolean
  added_to_ressort: boolean
  user_id: string
  name: string
  email: string
  card: {
    user_id: string
    name: string
    ressort: string
    role: string
    code: string
    may_drive: boolean
    printed: boolean
    printed_at: string | null
  }
}

export async function createGrossanlassHelper(
  departmentId: string,
  groupId: string,
  data: { email: string; name?: string; may_drive?: boolean },
): Promise<GrossanlassHelperResult> {
  const response = await apiClient.post<GrossanlassHelperResult>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/helpers`,
    data,
  )
  return response.data
}

/** Helferbedarf: Gesamtzahl, optional aufgeteilt in Funktionen (Aufteilung des Gesamtbedarfs, nicht zusätzlich). */
export type GrossanlassHelperNeed = {
  total: number
  functions: { label: string; count: number }[]
  /** Teil des Gesamtbedarfs ohne Funktion */
  unspecified: number
}

export async function getGrossanlassHelperNeed(departmentId: string, groupId: string): Promise<GrossanlassHelperNeed> {
  const { data } = await apiClient.get<GrossanlassHelperNeed>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/helper-need`,
  )
  return data
}

export async function saveGrossanlassHelperNeed(
  departmentId: string,
  groupId: string,
  data: { total: number; functions: { label: string; count: number }[] },
): Promise<GrossanlassHelperNeed> {
  const response = await apiClient.put<GrossanlassHelperNeed>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/helper-need`,
    data,
  )
  return response.data
}

/** Zusätzlicher Standortpunkt einer Gruppe: vorhandenen (place_id) zuordnen oder neu anlegen. */
export async function addGrossanlassGroupPlace(
  departmentId: string,
  groupId: string,
  data: { place_id?: string; name?: string; latitude?: number | null; longitude?: number | null },
): Promise<GaPlace[]> {
  const response = await apiClient.post<GaPlace[]>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/places`,
    data,
  )
  return response.data
}

export async function updateGrossanlassGroupPlace(
  departmentId: string,
  groupId: string,
  placeId: string,
  data: { name?: string; latitude?: number | null; longitude?: number | null },
): Promise<GaPlace[]> {
  const response = await apiClient.patch<GaPlace[]>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/places/${placeId}`,
    data,
  )
  return response.data
}

/** Entkoppelt den Standort von der Gruppe; der Standort selbst bleibt bestehen. */
export async function removeGrossanlassGroupPlace(
  departmentId: string,
  groupId: string,
  placeId: string,
): Promise<GaPlace[]> {
  const response = await apiClient.delete<GaPlace[]>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/places/${placeId}`,
  )
  return response.data
}
