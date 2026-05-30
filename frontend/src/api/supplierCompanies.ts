import apiClient from './apiClient'
import type { SupplierCompanyStatus, SupplierMembershipRole } from './supplier'

export interface SupplierCompanyAddress {
  id: string
  scope?: string
  type?: string
  name: string | null
  company: string | null
  address_line2?: string | null
  street: string | null
  street_number: string | null
  postal_code: string | null
  city: string | null
  canton: string | null
  country: string
  contact_first_name: string | null
  contact_last_name: string | null
  email: string | null
  phone: string | null
  mobile: string | null
  additional_info: string | null
  full_address?: string
}

export interface SupplierCompanyProfile {
  id: string
  name: string
  manufacturer_key: string | null
  supplier_address_id: string | null
  status: SupplierCompanyStatus
  capabilities: string[]
  linked_department_id: string | null
  address: SupplierCompanyAddress | null
  role: SupplierMembershipRole
  can_edit: boolean
  created_at: string
  updated_at: string
}

export interface SupplierCompanyPublic {
  id: string
  name: string
  manufacturer_key: string | null
  address: SupplierCompanyAddress | null
}

export interface SupplierCompanyProfilePatch {
  name?: string
  manufacturer_key?: string | null
  address?: Partial<
    Pick<
      SupplierCompanyAddress,
      | 'company'
      | 'name'
      | 'street'
      | 'street_number'
      | 'postal_code'
      | 'city'
      | 'canton'
      | 'country'
      | 'contact_first_name'
      | 'contact_last_name'
      | 'email'
      | 'phone'
      | 'mobile'
      | 'additional_info'
    >
  >
}

export async function listActiveSupplierCompanies(): Promise<{ supplier_companies: SupplierCompanyPublic[] }> {
  const { data } = await apiClient.get('/api/supplier-companies', { params: { status: 'active' } })
  return data
}

export async function getSupplierCompany(id: string): Promise<{ supplier_company: SupplierCompanyProfile }> {
  const { data } = await apiClient.get(`/api/supplier-companies/${id}`)
  return data
}

export async function patchSupplierCompany(
  id: string,
  payload: SupplierCompanyProfilePatch
): Promise<{ supplier_company: SupplierCompanyProfile; message: string }> {
  const { data } = await apiClient.patch(`/api/supplier-companies/${id}`, payload)
  return data
}
