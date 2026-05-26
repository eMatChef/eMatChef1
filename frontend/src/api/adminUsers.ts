import apiClient from './apiClient'
import type { AdminCapabilities, GlobalAdminRole } from '@/utils/adminCapabilities'

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
  global_admin_role: GlobalAdminRole | string
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
  global_admin_role: GlobalAdminRole | string
  admin_capabilities: AdminCapabilities
  admin_capabilities_stored: AdminCapabilities | null
}

export interface AdminUserUpdatePayload {
  first_name: string | null
  last_name: string | null
  nickname: string | null
  email: string
  state: string
  global_admin_role?: GlobalAdminRole
  admin_capabilities?: AdminCapabilities
  memberships: Array<{
    department_id: string
    role: DepartmentRole
    is_primary: boolean
  }>
}

export interface AdminOrgOverviewUser {
  id: string
  name: string
  email: string
  global_admin_role: GlobalAdminRole | string
  memberships: AdminUserMembership[]
  department_root_ids: string[]
}

export async function getAdminOrgOverview(): Promise<AdminOrgOverviewUser[]> {
  const { data } = await apiClient.get<{ users: AdminOrgOverviewUser[] }>('/api/users/admin/org-overview')
  return data.users
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

export async function getOrganisationsForAdmin(): Promise<Array<{ id: string; name: string }>> {
  const { data } = await apiClient.get<Array<{ id: string; name: string }>>('/api/organisations')
  return data
}
