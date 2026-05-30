import apiClient from './apiClient'
import type { SupplierMembershipRole } from './supplier'

export interface SupplierMembershipRow {
  supplier_company_id: string
  user_id: string
  role: SupplierMembershipRole
  is_primary: boolean
  name: string
  email: string | null
}

export interface SupplierJoinCodeData {
  supplier_company_id: string
  supplier_company_name: string
  join_code: string
  invite_url: string
  updated_at: string
}

export interface SupplierJoinResponse {
  supplier_company_id: string
  supplier_company_name: string
  role: SupplierMembershipRole
  auto_joined: boolean
  redirect_path: string
}

export async function joinSupplierCompany(joinCode: string): Promise<SupplierJoinResponse> {
  const { data } = await apiClient.post('/api/supplier-companies/join', { join_code: joinCode })
  return data
}

export async function listSupplierMemberships(companyId: string): Promise<{ memberships: SupplierMembershipRow[] }> {
  const { data } = await apiClient.get(`/api/supplier-companies/${companyId}/memberships`)
  return data
}

export async function updateSupplierMembershipRole(
  companyId: string,
  userId: string,
  role: SupplierMembershipRole
): Promise<{ membership: SupplierMembershipRow; message: string }> {
  const { data } = await apiClient.patch(`/api/supplier-companies/${companyId}/memberships/${userId}`, { role })
  return data
}

export async function removeSupplierMembership(companyId: string, userId: string): Promise<{ success: boolean }> {
  const { data } = await apiClient.delete(`/api/supplier-companies/${companyId}/memberships/${userId}`)
  return data
}

export async function getSupplierJoinCode(companyId: string): Promise<SupplierJoinCodeData> {
  const { data } = await apiClient.get(`/api/supplier-companies/${companyId}/join-code`)
  return data
}

export async function regenerateSupplierJoinCode(companyId: string): Promise<SupplierJoinCodeData> {
  const { data } = await apiClient.post(`/api/supplier-companies/${companyId}/join-code/regenerate`)
  return data
}
