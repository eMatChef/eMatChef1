import apiClient from './apiClient'
import type { SupplierCompanyStatus } from './supplier'

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
