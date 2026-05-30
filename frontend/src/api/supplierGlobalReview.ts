import apiClient from './apiClient'

export type SupplierReviewItemType = 'catalog' | 'template'

export interface SupplierGlobalReviewCatalogItem {
  id: string
  item_type: 'catalog'
  supplier_company_id: string
  supplier_company_name: string
  name: string
  sku: string | null
  tracking_type: string
  unit_price: number | null
  currency: string
  visibility: string
  status: string
  updated_at: string
}

export interface SupplierGlobalReviewTemplate {
  id: string
  item_type: 'template'
  supplier_company_id: string
  supplier_company_name: string
  name: string
  material_type: string
  unit_price: number | null
  currency: string
  component_count: number
  visibility: string
  status: string
  updated_at: string
}

export interface SupplierGlobalReviewResponse {
  catalog_items: SupplierGlobalReviewCatalogItem[]
  material_templates: SupplierGlobalReviewTemplate[]
}

export async function listSupplierGlobalReview(): Promise<SupplierGlobalReviewResponse> {
  const { data } = await apiClient.get<SupplierGlobalReviewResponse>(
    '/api/admin/supplier-global-review',
  )
  return data
}

export async function approveSupplierGlobalCatalogItem(
  itemId: string,
): Promise<{ item: SupplierGlobalReviewCatalogItem; message: string }> {
  const { data } = await apiClient.post(`/api/admin/supplier-global-review/catalog/${itemId}/approve`)
  return data
}

export async function rejectSupplierGlobalCatalogItem(
  itemId: string,
  reason?: string,
): Promise<{ item: SupplierGlobalReviewCatalogItem; message: string }> {
  const { data } = await apiClient.post(`/api/admin/supplier-global-review/catalog/${itemId}/reject`, {
    reason,
  })
  return data
}

export async function approveSupplierGlobalTemplate(
  templateId: string,
): Promise<{ item: SupplierGlobalReviewTemplate; message: string }> {
  const { data } = await apiClient.post(
    `/api/admin/supplier-global-review/templates/${templateId}/approve`,
  )
  return data
}

export async function rejectSupplierGlobalTemplate(
  templateId: string,
  reason?: string,
): Promise<{ item: SupplierGlobalReviewTemplate; message: string }> {
  const { data } = await apiClient.post(
    `/api/admin/supplier-global-review/templates/${templateId}/reject`,
    { reason },
  )
  return data
}
