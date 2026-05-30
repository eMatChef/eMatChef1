import apiClient from './apiClient'

export type SupplierCatalogTrackingType = 'bulk' | 'serialized'
export type SupplierCatalogVisibility = 'private' | 'departments' | 'global'
export type SupplierCatalogStatus = 'draft' | 'published' | 'pending_review'

export interface SupplierCatalogItem {
  id: string
  supplier_company_id: string
  name: string
  sku: string | null
  manufacturer: string | null
  tracking_type: SupplierCatalogTrackingType
  unit_price: number | null
  currency: string
  min_qty: number | null
  pack_size: number | null
  category_hint: string | null
  description: string | null
  external_ref: string | null
  is_active: boolean
  visibility: SupplierCatalogVisibility
  status: SupplierCatalogStatus
  created_at: string
  updated_at: string
  supplier_company_name?: string
}

export interface SupplierCatalogItemPayload {
  name: string
  sku?: string | null
  manufacturer?: string | null
  tracking_type?: SupplierCatalogTrackingType
  unit_price?: number | null
  currency?: string
  min_qty?: number | null
  pack_size?: number | null
  category_hint?: string | null
  description?: string | null
  external_ref?: string | null
  is_active?: boolean
  visibility?: SupplierCatalogVisibility
  status?: SupplierCatalogStatus
}

export async function listSupplierCatalogItems(
  companyId: string
): Promise<{ catalog_items: SupplierCatalogItem[] }> {
  const { data } = await apiClient.get(`/api/supplier-companies/${companyId}/catalog-items`)
  return data
}

export async function createSupplierCatalogItem(
  companyId: string,
  payload: SupplierCatalogItemPayload
): Promise<{ catalog_item: SupplierCatalogItem; message: string }> {
  const { data } = await apiClient.post(`/api/supplier-companies/${companyId}/catalog-items`, payload)
  return data
}

export async function updateSupplierCatalogItem(
  companyId: string,
  itemId: string,
  payload: Partial<SupplierCatalogItemPayload>
): Promise<{ catalog_item: SupplierCatalogItem; message: string }> {
  const { data } = await apiClient.patch(
    `/api/supplier-companies/${companyId}/catalog-items/${itemId}`,
    payload
  )
  return data
}

export async function deleteSupplierCatalogItem(
  companyId: string,
  itemId: string
): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.delete(
    `/api/supplier-companies/${companyId}/catalog-items/${itemId}`
  )
  return data
}
