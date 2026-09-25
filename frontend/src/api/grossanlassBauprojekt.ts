import apiClient from './apiClient'
import type { GrossanlassGroup } from './grossanlassGroups'
import type { GrossanlassWishLine } from './grossanlassWishes'
import type { GaLogisticsPack, GaMap, GaPlace } from './grossanlassLogistics'

export type GaBauprojektTask = {
  id: string
  group_id: string
  title: string
  description?: string | null
  sort_order: number
  starts_at?: string | null
  duration_minutes?: number | null
  assignee_user_id?: string | null
  created_at: string
}

export type GaBauprojektEinsatz = {
  id: string
  qty: number
  from: string
  to: string
  status: string
  delivery: string
  who: string
  object_id?: string | null
  object_name: string
  wish_line_id: string | null
  kind?: string
}

export type GaBauprojektBriefing = {
  group: Pick<GrossanlassGroup, 'id' | 'name' | 'department_id' | 'parent_id' | 'kind'> & {
    window_start?: string | null
    window_end?: string | null
    build_status?: string | null
    description?: string | null
  } | null
  window_start: string | null
  window_end: string | null
  build_status?: string | null
  description?: string | null
  place: GaPlace | null
  tasks: GaBauprojektTask[]
  material: GrossanlassWishLine[]
  direct_material?: Array<{
    id: string
    label: string
    quantity: number
    notes?: string | null
    status: string
    source: 'direct'
    self_organized?: boolean
    pickup_need?: 'can' | 'must' | null
    pickup_place?: string | null
    return_needed?: boolean
    quantity_unit?: string | null
  }>
  packs: GaLogisticsPack[]
  einsaetze?: GaBauprojektEinsatz[]
  map: Pick<GaMap, 'id' | 'name' | 'image_url' | 'image_width' | 'image_height'> & {
    bounds_north?: number | null
    bounds_south?: number | null
    bounds_east?: number | null
    bounds_west?: number | null
  } | null
  can_edit: boolean
}

export async function getGrossanlassBauprojekt(
  departmentId: string,
  groupId: string,
): Promise<GaBauprojektBriefing> {
  const { data } = await apiClient.get<GaBauprojektBriefing>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/bauprojekt`,
  )
  return data
}

export async function patchGrossanlassBauprojektWindow(
  departmentId: string,
  groupId: string,
  data: {
    window_start?: string | null
    window_end?: string | null
    build_status?: string | null
    description?: string | null
  },
): Promise<GaBauprojektBriefing> {
  const { data: out } = await apiClient.patch<GaBauprojektBriefing>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/bauprojekt`,
    data,
  )
  return out
}

export async function createGrossanlassBauprojektTask(
  departmentId: string,
  groupId: string,
  payload: { title: string; description?: string | null; starts_at?: string | null; duration_minutes?: number | null; assignee_user_id?: string | null },
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks`,
    payload,
  )
  return data
}

export async function updateGrossanlassBauprojektTask(
  departmentId: string,
  groupId: string,
  taskId: string,
  payload: { title?: string; description?: string | null; starts_at?: string | null; duration_minutes?: number | null; assignee_user_id?: string | null; sort_order?: number },
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.patch<GaBauprojektTask>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks/${taskId}`,
    payload,
  )
  return data
}

export async function deleteGrossanlassBauprojektTask(
  departmentId: string,
  groupId: string,
  taskId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks/${taskId}`,
  )
}

export async function updateGrossanlassBauprojektMaterial(
  departmentId: string,
  groupId: string,
  lineId: string,
  payload: {
    label: string
    quantity?: number
    mode?: 'wish' | 'direct'
    pickup_need?: 'can' | 'must' | null
    pickup_place?: string | null
    return_needed?: boolean
    quantity_unit?: string | null
  },
): Promise<void> {
  await apiClient.patch(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/material/${lineId}`,
    payload,
  )
}

export async function addGrossanlassBauprojektMaterial(
  departmentId: string,
  groupId: string,
  payload: {
    label: string
    quantity?: number
    location?: string
    notes?: string | null
    mode?: 'wish' | 'direct'
    pickup_need?: 'can' | 'must' | null
    pickup_place?: string | null
    return_needed?: boolean
    quantity_unit?: string | null
  },
): Promise<GrossanlassWishLine> {
  const { data } = await apiClient.post<GrossanlassWishLine>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/material`,
    payload,
  )
  return data
}

export async function getGrossanlassPlaceBriefing(
  departmentId: string,
  placeId: string,
): Promise<GaBauprojektBriefing> {
  const { data } = await apiClient.get<GaBauprojektBriefing>(
    `/api/departments/${departmentId}/grossanlass/places/${placeId}/briefing`,
  )
  return data
}
