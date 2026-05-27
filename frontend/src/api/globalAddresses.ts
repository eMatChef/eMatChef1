import apiClient from './apiClient'
import type { Address } from './addresses'

export interface GlobalAddress extends Address {
  is_default?: boolean
}

export interface GlobalAddressFormData {
  name?: string | null
  company?: string | null
  street?: string | null
  street_number?: string | null
  postal_code?: string | null
  city?: string | null
  canton?: string | null
  country?: string | null
  contact_first_name?: string | null
  contact_last_name?: string | null
  email?: string | null
  phone?: string | null
  mobile?: string | null
  additional_info?: string | null
}

export async function getGlobalAddresses(q?: string): Promise<{ addresses: GlobalAddress[] }> {
  const params: Record<string, string> = {}
  if (q?.trim()) params.q = q.trim()

  const { data } = await apiClient.get('/api/global-addresses', { params })
  return data
}

export async function createGlobalAddress(formData: GlobalAddressFormData): Promise<{
  address: GlobalAddress
  message: string
}> {
  const { data } = await apiClient.post('/api/global-addresses', formData)
  return data
}

export async function updateGlobalAddress(id: string, formData: GlobalAddressFormData): Promise<{
  address: GlobalAddress
  message: string
}> {
  const { data } = await apiClient.put(`/api/global-addresses/${id}`, formData)
  return data
}

export async function deleteGlobalAddress(id: string): Promise<{ message: string }> {
  const { data } = await apiClient.delete(`/api/global-addresses/${id}`)
  return data
}
