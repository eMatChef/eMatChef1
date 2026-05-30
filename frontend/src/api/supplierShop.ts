import apiClient from './apiClient'
import type { SupplierCatalogItem } from './supplierCatalog'

export interface SupplierShopCompany {
  id: string
  name: string
  manufacturer_key: string | null
  capabilities: string[]
}

export interface SupplierShopTemplate {
  id: string
  supplier_company_id: string
  name: string
  material_type: string
  unit_price: number | null
  currency: string
  component_count: number
}

export interface WatchlistItem {
  catalog_item_id: string
  name: string
  sku: string | null
  qty: number
  unit_price: number | null
  currency: string
  tracking_type: 'bulk' | 'serialized'
  supplier_company_id: string
  supplier_company_name: string
}

export interface CatalogImportPayload {
  catalog_item_id: string
  qty: number
  category_id?: string | null
  storage_address_id?: string | null
  purchase_date?: string | null
  serial_numbers?: string[]
}

export interface DeliveryImportPayload {
  category_id?: string | null
  storage_address_id?: string | null
  purchase_date?: string | null
  lines?: Array<{
    line_id: string
    serial_numbers?: string[]
  }>
}

export interface TemplateImportPayload {
  supplier_material_template_id: string
  name?: string
  category_id?: string | null
  storage_address_id?: string | null
  purchase_date?: string | null
  serial_number?: string | null
  components?: Array<{
    component_type: string
    serial_number?: string
    qty?: number
  }>
}

export async function listSupplierShopCompanies(
  departmentId: string,
): Promise<SupplierShopCompany[]> {
  const { data } = await apiClient.get<{ companies: SupplierShopCompany[] }>(
    `/api/departments/${departmentId}/supplier-shop/companies`,
  )
  return data.companies
}

export async function listSupplierShopCatalog(
  departmentId: string,
  supplierCompanyId: string,
): Promise<SupplierCatalogItem[]> {
  const { data } = await apiClient.get<{ catalog_items: SupplierCatalogItem[] }>(
    `/api/departments/${departmentId}/supplier-shop/catalog`,
    { params: { supplier_company_id: supplierCompanyId } },
  )
  return data.catalog_items
}

export async function listSupplierShopTemplates(
  departmentId: string,
  supplierCompanyId: string,
): Promise<SupplierShopTemplate[]> {
  const { data } = await apiClient.get<{ material_templates: SupplierShopTemplate[] }>(
    `/api/departments/${departmentId}/supplier-shop/templates`,
    { params: { supplier_company_id: supplierCompanyId } },
  )
  return data.material_templates
}

export async function importSupplierCatalogItem(
  departmentId: string,
  payload: CatalogImportPayload,
): Promise<{ material: Record<string, unknown>; message: string }> {
  const { data } = await apiClient.post(
    `/api/departments/${departmentId}/supplier-shop/catalog-import`,
    payload,
  )
  return data
}

export async function importSupplierTemplate(
  departmentId: string,
  payload: TemplateImportPayload,
): Promise<{ material: Record<string, unknown>; message: string }> {
  const { data } = await apiClient.post(
    `/api/departments/${departmentId}/supplier-shop/template-import`,
    payload,
  )
  return data
}

export async function importSupplierDelivery(
  departmentId: string,
  deliveryId: string,
  payload: DeliveryImportPayload = {},
): Promise<{ delivery: Record<string, unknown>; materials: Record<string, unknown>[]; message: string }> {
  const { data } = await apiClient.post(
    `/api/departments/${departmentId}/supplier-deliveries/${deliveryId}/import`,
    payload,
  )
  return data
}

const WATCHLIST_KEY = 'supplierShopWatchlist'

export function loadWatchlist(departmentId: string): WatchlistItem[] {
  try {
    const raw = localStorage.getItem(`${WATCHLIST_KEY}:${departmentId}`)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

export function saveWatchlist(departmentId: string, items: WatchlistItem[]): void {
  localStorage.setItem(`${WATCHLIST_KEY}:${departmentId}`, JSON.stringify(items))
}

export function watchlistBudgetTotal(items: WatchlistItem[]): number {
  return items.reduce((sum, item) => sum + (item.unit_price ?? 0) * item.qty, 0)
}
