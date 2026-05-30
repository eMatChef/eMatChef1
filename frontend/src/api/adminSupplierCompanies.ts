import apiClient from './apiClient'
import type { SupplierCompanyStatus, SupplierMembershipRole } from './supplier'

export interface AdminSupplierCompany {
  id: string
  name: string
  manufacturer_key: string | null
  supplier_address_id: string | null
  capabilities: string[]
  linked_department_id: string | null
  status: SupplierCompanyStatus
  created_at: string
  updated_at: string
  address?: Record<string, unknown> | null
  membership_count?: number
}

export interface AdminSupplierMembership {
  supplier_company_id: string
  user_id: string
  role: SupplierMembershipRole
  is_primary: boolean
  name: string
  email: string | null
}

export interface CreateSupplierCompanyPayload {
  name: string
  manufacturer_key?: string | null
  status?: SupplierCompanyStatus
  capabilities?: string[]
  linked_department_id?: string | null
  address?: Record<string, string | null | undefined>
  admin_user_email?: string | null
  admin_user_id?: string | null
}

export interface PromoteGlobalAddressPayload {
  name?: string | null
  manufacturer_key?: string | null
  status?: SupplierCompanyStatus
  capabilities?: string[]
  linked_department_id?: string | null
  admin_user_email?: string | null
  admin_user_id?: string | null
}

export async function listAdminSupplierCompanies(): Promise<{ supplier_companies: AdminSupplierCompany[] }> {
  const { data } = await apiClient.get('/api/admin/supplier-companies')
  return data
}

export async function createAdminSupplierCompany(payload: CreateSupplierCompanyPayload): Promise<{
  supplier_company: AdminSupplierCompany
  message: string
}> {
  const { data } = await apiClient.post('/api/admin/supplier-companies', payload)
  return data
}

export async function promoteGlobalAddressToSupplierCompany(
  addressId: string,
  payload: PromoteGlobalAddressPayload
): Promise<{ supplier_company: AdminSupplierCompany; message: string }> {
  const { data } = await apiClient.post(
    `/api/admin/supplier-companies/promote-global-address/${addressId}`,
    payload
  )
  return data
}

export async function patchAdminSupplierCompany(
  id: string,
  payload: Partial<CreateSupplierCompanyPayload>
): Promise<{ supplier_company: AdminSupplierCompany; message: string }> {
  const { data } = await apiClient.patch(`/api/admin/supplier-companies/${id}`, payload)
  return data
}

export async function deleteAdminSupplierCompany(id: string): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.delete(`/api/admin/supplier-companies/${id}`)
  return data
}

export async function listAdminSupplierMemberships(
  companyId: string
): Promise<{ memberships: AdminSupplierMembership[] }> {
  const { data } = await apiClient.get(`/api/admin/supplier-companies/${companyId}/memberships`)
  return data
}

export async function addAdminSupplierMembership(
  companyId: string,
  payload: { user_email?: string; user_id?: string; role?: SupplierMembershipRole }
): Promise<{ membership: AdminSupplierMembership; message: string }> {
  const { data } = await apiClient.post(`/api/admin/supplier-companies/${companyId}/memberships`, payload)
  return data
}

export async function updateAdminSupplierMembership(
  companyId: string,
  userId: string,
  payload: { role: SupplierMembershipRole }
): Promise<{ membership: AdminSupplierMembership; message: string }> {
  const { data } = await apiClient.patch(
    `/api/admin/supplier-companies/${companyId}/memberships/${userId}`,
    payload
  )
  return data
}

export async function removeAdminSupplierMembership(
  companyId: string,
  userId: string
): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.delete(`/api/admin/supplier-companies/${companyId}/memberships/${userId}`)
  return data
}
