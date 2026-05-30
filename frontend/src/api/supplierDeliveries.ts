import apiClient from './apiClient'

export type SupplierDeliveryStatus = 'draft' | 'submitted' | 'imported' | 'cancelled'

export interface SupplierDeliveryLine {
  id?: string
  delivery_id?: string
  catalog_item_id: string
  catalog_item_name?: string
  catalog_item_sku?: string | null
  tracking_type?: 'bulk' | 'serialized'
  qty: number
  unit_price: number | null
  serial_numbers: string[]
  component_serials?: Record<string, unknown>[] | null
  sort_order?: number
}

export interface SupplierDelivery {
  id: string
  supplier_company_id: string
  supplier_company_name: string
  department_id: string
  department_name: string
  delivery_ref: string | null
  invoice_ref: string | null
  delivered_at: string | null
  status: SupplierDeliveryStatus
  notes: string | null
  created_at: string
  updated_at: string
  lines: SupplierDeliveryLine[]
}

export interface SupplierDeliveryPayload {
  department_id: string
  delivery_ref?: string | null
  invoice_ref?: string | null
  delivered_at?: string | null
  notes?: string | null
  lines: Array<{
    catalog_item_id: string
    qty: number
    unit_price?: number | null
    serial_numbers?: string[]
    component_serials?: Record<string, unknown>[] | null
    sort_order?: number
  }>
}

export async function listSupplierDeliveries(companyId: string): Promise<{ deliveries: SupplierDelivery[] }> {
  const { data } = await apiClient.get(`/api/supplier-companies/${companyId}/deliveries`)
  return data
}

export async function createSupplierDelivery(
  companyId: string,
  payload: SupplierDeliveryPayload
): Promise<{ delivery: SupplierDelivery; message: string }> {
  const { data } = await apiClient.post(`/api/supplier-companies/${companyId}/deliveries`, payload)
  return data
}

export async function updateSupplierDelivery(
  companyId: string,
  deliveryId: string,
  payload: Partial<SupplierDeliveryPayload>
): Promise<{ delivery: SupplierDelivery; message: string }> {
  const { data } = await apiClient.patch(
    `/api/supplier-companies/${companyId}/deliveries/${deliveryId}`,
    payload
  )
  return data
}

export async function submitSupplierDelivery(
  companyId: string,
  deliveryId: string
): Promise<{ delivery: SupplierDelivery; message: string; warnings?: string[] }> {
  const { data } = await apiClient.post(
    `/api/supplier-companies/${companyId}/deliveries/${deliveryId}/submit`
  )
  return data
}

export async function cancelSupplierDelivery(
  companyId: string,
  deliveryId: string
): Promise<{ delivery: SupplierDelivery; message: string }> {
  const { data } = await apiClient.post(
    `/api/supplier-companies/${companyId}/deliveries/${deliveryId}/cancel`
  )
  return data
}

export async function deleteSupplierDelivery(
  companyId: string,
  deliveryId: string
): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.delete(
    `/api/supplier-companies/${companyId}/deliveries/${deliveryId}`
  )
  return data
}

export async function listDepartmentSupplierDeliveries(
  departmentId: string,
  status: 'submitted' | 'imported' | 'all' = 'submitted'
): Promise<{ deliveries: SupplierDelivery[] }> {
  const { data } = await apiClient.get(`/api/departments/${departmentId}/supplier-deliveries`, {
    params: { status },
  })
  return data
}

export async function getDepartmentSupplierDelivery(
  departmentId: string,
  deliveryId: string
): Promise<{ delivery: SupplierDelivery }> {
  const { data } = await apiClient.get(
    `/api/departments/${departmentId}/supplier-deliveries/${deliveryId}`
  )
  return data
}
