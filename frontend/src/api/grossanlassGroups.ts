import apiClient from './apiClient'
import type { GroupMember } from './groups'
import type { GaPlace, GaPolygonPoint } from './grossanlassLogistics'

export type GrossanlassGroupKind = 'ressort' | 'teilbereich'
export type GrossanlassNodeType = 'ressort' | 'unterressort' | 'bauprojekt'

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
  description?: string | null
  place?: GaPlace | null
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
