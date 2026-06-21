import apiClient from './apiClient'

export type StorageQrScope = 'all' | 'address' | 'rack' | 'slot'

export interface StorageQrPdfRack {
  id: string
  name: string
  slots: Array<{ id: string; name: string }>
}

export interface StorageQrSelectionItem {
  entity_type: 'storage_address' | 'storage_rack' | 'storage_slot' | string
  entity_id: string
}

export interface StorageQrPayload {
  entity_type: 'storage_address' | 'storage_rack' | 'storage_slot'
  entity_id: string
  public_code: string
  public_url: string
  label: string
}

export interface StorageQrQueueResult {
  created_count: number
  skipped_count: number
  items: Array<{
    id: string
    department_id: string
    entity_type: string
    entity_id: string
    label: string
    public_code?: string | null
    public_url: string
    status: string
    created_at: string
  }>
}

export async function ensureStorageAddressQr(addressId: string): Promise<StorageQrPayload> {
  const response = await apiClient.post<StorageQrPayload>(`/api/storage-qr/addresses/${encodeURIComponent(addressId)}/ensure`)
  return response.data
}

export async function ensureStorageRackQr(rackId: string): Promise<StorageQrPayload> {
  const response = await apiClient.post<StorageQrPayload>(`/api/storage-qr/racks/${encodeURIComponent(rackId)}/ensure`)
  return response.data
}

export async function ensureStorageSlotQr(slotId: string): Promise<StorageQrPayload> {
  const response = await apiClient.post<StorageQrPayload>(`/api/storage-qr/slots/${encodeURIComponent(slotId)}/ensure`)
  return response.data
}

export async function queueStorageQrPrint(
  departmentId: string,
  scope: StorageQrScope,
  options?: { addressId?: string; rackId?: string; slotId?: string },
): Promise<StorageQrQueueResult> {
  const response = await apiClient.post<StorageQrQueueResult>('/api/storage-qr/queue-print', {
    department_id: departmentId,
    scope,
    address_id: options?.addressId,
    rack_id: options?.rackId,
    slot_id: options?.slotId,
  })
  return response.data
}

export async function lookupStorageQr(
  departmentId: string,
  kind: 'l' | 'r' | 's',
  code: string,
): Promise<Record<string, unknown>> {
  const response = await apiClient.get<Record<string, unknown>>(
    `/api/storage-qr/lookup/${kind}/${encodeURIComponent(code)}?department_id=${encodeURIComponent(departmentId)}`,
  )
  return response.data
}

export async function downloadStorageQrPdf(
  departmentId: string,
  addressId: string,
  selections: StorageQrSelectionItem[],
): Promise<Blob> {
  const response = await apiClient.post(
    '/api/storage-qr/pdf',
    {
      department_id: departmentId,
      address_id: addressId,
      selections,
    },
    { responseType: 'blob' },
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
