import apiClient from './apiClient'

export type DepartmentRole = 'mw' | 'dc' | 'l1' | 'l2' | 'l3' | 'u'

export interface AdminUserListItem {
  id: string
  profile_id: string
  name: string
  first_name: string | null
  last_name: string | null
  nickname: string | null
  email: string
  state: string
  created_at: string
  departments_count: number
}

export interface AdminUserMembership {
  department_id: string
  department_name: string
  role: DepartmentRole
  is_primary: boolean
}

export interface AdminUserDetail {
  id: string
  profile_id: string
  name: string
  first_name: string | null
  last_name: string | null
  nickname: string | null
  email: string
  state: string
  created_at: string
  memberships: AdminUserMembership[]
}

export interface AdminUserUpdatePayload {
  first_name: string | null
  last_name: string | null
  nickname: string | null
  email: string
  state: string
  memberships: Array<{
    department_id: string
    role: DepartmentRole
    is_primary: boolean
  }>
}

export async function getAdminUsers(params?: {
  q?: string
  sortBy?: 'created_at' | 'name' | 'email' | 'departments_count'
  sortDir?: 'asc' | 'desc'
}): Promise<AdminUserListItem[]> {
  const { data } = await apiClient.get<AdminUserListItem[]>('/api/users/admin/list', {
    params
  })
  return data
}

export async function getAdminUserDetail(userId: string): Promise<AdminUserDetail> {
  const { data } = await apiClient.get<AdminUserDetail>(`/api/users/${userId}/admin-detail`)
  return data
}

export async function updateAdminUser(userId: string, payload: AdminUserUpdatePayload): Promise<AdminUserDetail> {
  const { data } = await apiClient.patch<AdminUserDetail>(`/api/users/${userId}/admin`, payload)
  return data
}
