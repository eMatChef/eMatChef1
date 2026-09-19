import apiClient from './apiClient'
import type { GrossanlassGroup } from './grossanlassGroups'
import type { GrossanlassWishLine } from './grossanlassWishes'
import type { GaLogisticsPack, GaMap, GaPlace } from './grossanlassLogistics'

export type GaBauprojektTask = {
  id: string
  group_id: string
  title: string
  sort_order: number
  created_at: string
}

export type GaBauprojektBriefing = {
  group: Pick<GrossanlassGroup, 'id' | 'name' | 'department_id' | 'parent_id' | 'kind'> & {
    window_start?: string | null
    window_end?: string | null
  } | null
  window_start: string | null
  window_end: string | null
  place: GaPlace | null
  tasks: GaBauprojektTask[]
  material: GrossanlassWishLine[]
  packs: GaLogisticsPack[]
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
  data: { window_start?: string | null; window_end?: string | null },
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
  title: string,
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks`,
    { title },
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

export async function addGrossanlassBauprojektMaterial(
  departmentId: string,
  groupId: string,
  payload: { label: string; quantity?: number; location?: string },
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
