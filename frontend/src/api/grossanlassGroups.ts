import apiClient from './apiClient'
import type { GroupMember } from './groups'

export type GrossanlassGroupKind = 'ressort' | 'teilbereich'
export type GrossanlassNodeType = 'ressort' | 'unterressort' | 'bauprojekt'

export interface GrossanlassGroup {
  id: string
  name: string
  department_id: string
  parent_id: string | null
  sort_order: number
  level: number
  kind: GrossanlassGroupKind
  node_type: GrossanlassNodeType
  member_count: number
  leader_count: number
  members: GroupMember[]
  leaders: GroupMember[]
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

export async function addGrossanlassGroupMember(
  departmentId: string,
  groupId: string,
  data: {
    user_id: string
    role?: string
    is_primary?: boolean
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
