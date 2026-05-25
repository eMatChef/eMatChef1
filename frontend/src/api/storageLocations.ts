import apiClient from './apiClient'

export interface StorageRack {
  id: string
  department_id: string
  storage_address_id: string | null
  storage_address_name?: string
  name: string
  sort_order: number
  is_active: boolean
}

export interface StorageSlot {
  id: string
  rack_id: string
  name: string
  sort_order: number
  is_active: boolean
}

export interface RackContentsItem {
  material_id: string
  material_name: string
  tracking_type: 'serialized' | 'bulk' | null
  qty: number
}

export interface RackContentsResponse {
  rack_id: string
  rack_name: string
  contents: RackContentsItem[]
}

/** Inhalt einer Kiste (Allokationen mit container_batch_id = diese Kiste) */
export interface ContainerBatchContentsResponse {
  container_batch_id: string
  container_label: string
  contents: RackContentsItem[]
}

export interface StorageSlotContent {
  material_id: string
  material_name: string
  batch_id: string
  allocation_id: string | null
  container_batch_id?: string | null
  container_label?: string | null
  qty: number
  tracking_type: 'serialized' | 'bulk' | null
}

export interface StorageOverviewSlot {
  id: string | null
  name: string
  contents: StorageSlotContent[]
}

export interface StorageOverviewRack {
  id: string
  name: string
  storage_address_id?: string | null
  storage_address_name?: string
  slots: StorageOverviewSlot[]
}

export interface StorageOverviewResponse {
  racks: StorageOverviewRack[]
}

/** Kurzvorschau Inhalt (GET /container-batches): max. 2 Artikel, Rest über content_preview_more */
export interface ContainerBatchContentPreviewLine {
  material_name: string
  qty: number
}

export interface ContainerBatch {
  id: string
  material_id?: string
  serial_number: string | null
  label: string | null
  material_name: string
  display_label: string
  rack_id: string
  slot_id: string | null
  rack: { id: string; name: string } | null
  slot: { id: string; name: string } | null
  content_preview?: ContainerBatchContentPreviewLine[]
  content_preview_more?: number
  /** true = kein Lagerinhalt in der Kiste (bei Packliste mit activity_id) */
  storage_empty?: boolean
  /** Physische Kombo, die diese Kiste als Referenz nutzt (bei for_material_id) */
  physical_combo_id?: string | null
  physical_combo_name?: string | null
}

/**
 * @param options.activityId — Packliste: nur leere Kisten (Lagerinhalt), nicht schon dieser Aktivität zugeordnet;
 *   mit Planungs-/Nutzungszeitraum zusätzlich keine, die parallel anderer Aktivität gebucht sind (leer und frei, nicht «oder»).
 */
export async function getContainerBatches(
  departmentId: string,
  options?: { activityId?: string; forMaterialId?: string },
): Promise<ContainerBatch[]> {
  const params = new URLSearchParams({ department_id: departmentId })
  if (options?.activityId) {
    params.append('activity_id', options.activityId)
  }
  if (options?.forMaterialId) {
    params.append('for_material_id', options.forMaterialId)
  }
  const response = await apiClient.get<ContainerBatch[]>(`/api/container-batches?${params.toString()}`)
  return response.data
}

export async function getStorageOverview(departmentId: string): Promise<StorageOverviewResponse> {
  const response = await apiClient.get<StorageOverviewResponse>(
    `/api/storage-overview?department_id=${encodeURIComponent(departmentId)}`
  )
  return response.data
}

export async function getStorageRacks(departmentId: string, storageAddressId?: string): Promise<StorageRack[]> {
  const params = new URLSearchParams({ department_id: departmentId })
  if (storageAddressId) params.append('storage_address_id', storageAddressId)
  const response = await apiClient.get<StorageRack[]>(`/api/storage-racks?${params.toString()}`)
  return response.data
}

export async function getRackContents(rackId: string): Promise<RackContentsResponse> {
  const response = await apiClient.get<RackContentsResponse>(`/api/storage-racks/${rackId}/contents`)
  return response.data
}

export async function getContainerBatchContents(containerBatchId: string): Promise<ContainerBatchContentsResponse> {
  const response = await apiClient.get<ContainerBatchContentsResponse>(
    `/api/container-batches/${encodeURIComponent(containerBatchId)}/contents`
  )
  return response.data
}

export async function createStorageRack(data: {
  department_id: string
  storage_address_id: string
  name: string
  initial_slot_name?: string
  sort_order?: number
  is_active?: boolean
}): Promise<StorageRack> {
  const response = await apiClient.post<StorageRack>('/api/storage-racks', data)
  return response.data
}

export async function updateStorageRack(id: string, data: Partial<{
  storage_address_id: string
  name: string
  sort_order: number
  is_active: boolean
}>): Promise<StorageRack> {
  const response = await apiClient.patch<StorageRack>(`/api/storage-racks/${id}`, data)
  return response.data
}

export async function deleteStorageRack(id: string): Promise<void> {
  await apiClient.delete(`/api/storage-racks/${id}`)
}

export async function getStorageSlots(rackId: string): Promise<StorageSlot[]> {
  const response = await apiClient.get<unknown>(`/api/storage-slots?rack_id=${encodeURIComponent(rackId)}`)
  const raw = response.data
  return Array.isArray(raw) ? (raw as StorageSlot[]) : []
}

export async function createStorageSlot(data: {
  rack_id: string
  name: string
  sort_order?: number
  is_active?: boolean
}): Promise<StorageSlot> {
  const response = await apiClient.post<StorageSlot>('/api/storage-slots', data)
  return response.data
}

export async function updateStorageSlot(id: string, data: Partial<{
  name: string
  sort_order: number
  is_active: boolean
}>): Promise<StorageSlot> {
  const response = await apiClient.patch<StorageSlot>(`/api/storage-slots/${id}`, data)
  return response.data
}

export async function deleteStorageSlot(id: string): Promise<void> {
  await apiClient.delete(`/api/storage-slots/${id}`)
}

