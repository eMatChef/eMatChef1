import apiClient from './apiClient'

/** entity_type: batch | activity | workshop — public_url muss kanonisches QR-Schema sein */
export interface PrintCartItem {
  id: string
  department_id: string
  created_by_user_id?: string | null
  entity_type: 'batch' | 'activity' | 'workshop' | string
  entity_id: string
  label: string
  public_code?: string | null
  public_url: string
  status: string
  created_at: string
  printed_at?: string | null
}

export interface AddPrintCartItemRequest {
  department_id: string
  entity_type: string
  entity_id: string
  label: string
  public_code?: string | null
  public_url: string
}

export async function getPrintCartItems(departmentId: string): Promise<PrintCartItem[]> {
  const response = await apiClient.get<PrintCartItem[]>(`/api/tasks/print-cart?department_id=${encodeURIComponent(departmentId)}`)
  return response.data
}

export async function addPrintCartItem(payload: AddPrintCartItemRequest): Promise<{ created: boolean; item: PrintCartItem }> {
  const response = await apiClient.post<{ created: boolean; item: PrintCartItem }>('/api/tasks/print-cart/items', payload)
  return response.data
}

export async function addPrintCartItemsBulk(
  departmentId: string,
  items: AddPrintCartItemRequest[]
): Promise<{ created_count: number; skipped_count: number; items: PrintCartItem[] }> {
  const response = await apiClient.post<{ created_count: number; skipped_count: number; items: PrintCartItem[] }>(
    '/api/tasks/print-cart/bulk',
    { department_id: departmentId, items }
  )
  return response.data
}

export async function markPrintCartItemPrinted(id: string): Promise<void> {
  await apiClient.patch(`/api/tasks/print-cart/items/${id}/printed`)
}

export async function deletePrintCartItem(id: string): Promise<void> {
  await apiClient.delete(`/api/tasks/print-cart/items/${id}`)
}

export async function clearPrintCart(departmentId: string): Promise<{ deleted: number }> {
  const response = await apiClient.delete<{ deleted: number }>(
    `/api/tasks/print-cart?department_id=${encodeURIComponent(departmentId)}`
  )
  return response.data
}

export async function downloadMaterialQrPdf(departmentId: string): Promise<Blob> {
  const response = await apiClient.get(
    `/api/tasks/print-cart/material-qr-pdf?department_id=${encodeURIComponent(departmentId)}`,
    { responseType: 'blob' }
  )
  const contentType = String(response.headers['content-type'] || '')
  if (contentType.includes('application/json')) {
    const text = await (response.data as Blob).text()
    let message = 'PDF-Export fehlgeschlagen'
    try {
      const parsed = JSON.parse(text) as { error?: string }
      if (parsed.error) message = parsed.error
    } catch {
      /* ignore */
    }
    throw new Error(message)
  }
  return response.data as Blob
}

