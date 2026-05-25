import apiClient from './apiClient'

export interface ActivityPackContainer {
  id: string
  activity_id: string
  container_batch_id: string | null
  /** Material-ID der zugeordneten Kisten-Charge (wenn container_batch_id gesetzt) */
  container_material_item_id?: string | null
  label: string
  status: string
}

export interface ActivityPackContainerItem {
  id: string
  pack_container_id: string
  material_item_id: string
  material_batch_id: string | null
  quantity_packed: number
  quantity_transport_to?: number
  quantity_issued: number
  quantity_transport_back?: number
  quantity_returned: number
  quantity_stored?: number
  condition_out: string
  notes: string | null
  material_name?: string
  serial_number?: string | null
  batch_label?: string | null
}

function num(v: unknown, fallback = 0): number {
  const n = typeof v === 'number' ? v : parseInt(String(v ?? ''), 10)
  return Number.isFinite(n) ? n : fallback
}

function mapPackContainerItem(raw: Record<string, unknown>): ActivityPackContainerItem {
  return {
    id: String(raw.id ?? ''),
    pack_container_id: String(raw.pack_container_id ?? ''),
    material_item_id: String(raw.material_item_id ?? ''),
    material_batch_id: raw.material_batch_id != null ? String(raw.material_batch_id) : null,
    quantity_packed: num(raw.quantity_packed),
    quantity_transport_to: num(raw.quantity_transport_to),
    quantity_issued: num(raw.quantity_issued),
    quantity_transport_back: num(raw.quantity_transport_back),
    quantity_returned: num(raw.quantity_returned),
    quantity_stored: raw.quantity_stored != null ? num(raw.quantity_stored) : undefined,
    condition_out: String(raw.condition_out ?? 'ok'),
    notes: raw.notes != null ? String(raw.notes) : null,
    material_name: raw.material_name != null ? String(raw.material_name) : undefined,
    serial_number: raw.serial_number != null ? String(raw.serial_number) : null,
    batch_label: raw.batch_label != null ? String(raw.batch_label) : null,
  }
}

export async function getActivityPackContainers(activityId: string): Promise<ActivityPackContainer[]> {
  const response = await apiClient.get<ActivityPackContainer[]>(`/api/activities/${activityId}/pack-containers`)
  return response.data
}

export async function createActivityPackContainer(activityId: string, data: {
  label: string
  status?: string
  container_batch_id?: string | null
}): Promise<ActivityPackContainer> {
  const response = await apiClient.post<ActivityPackContainer>(`/api/activities/${activityId}/pack-containers`, data)
  return response.data
}

export async function updateActivityPackContainer(activityId: string, containerId: string, data: Partial<{
  label: string
  status: string
  container_batch_id: string | null
}>): Promise<ActivityPackContainer> {
  const response = await apiClient.patch<ActivityPackContainer>(`/api/activities/${activityId}/pack-containers/${containerId}`, data)
  return response.data
}

export async function deleteActivityPackContainer(activityId: string, containerId: string): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/pack-containers/${containerId}`)
}

export async function getActivityPackContainerItems(activityId: string, containerId: string): Promise<ActivityPackContainerItem[]> {
  const response = await apiClient.get<Record<string, unknown>[]>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items`,
  )
  return response.data.map((row) => mapPackContainerItem(row))
}

export async function createActivityPackContainerItem(
  activityId: string,
  containerId: string,
  data: {
    material_item_id: string
    material_batch_id?: string | null
    quantity_packed?: number
    quantity_issued?: number
    quantity_returned?: number
    condition_out?: string
    notes?: string | null
  }
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.post<ActivityPackContainerItem>(`/api/activities/${activityId}/pack-containers/${containerId}/items`, data)
  return response.data
}

export async function updateActivityPackContainerItem(
  activityId: string,
  containerId: string,
  itemId: string,
  data: Partial<{
    material_batch_id: string | null
    quantity_packed: number
    quantity_issued: number
    quantity_returned: number
    quantity_stored?: number
    condition_out: string
    notes: string | null
  }>
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.patch<ActivityPackContainerItem>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}`,
    data
  )
  return response.data
}

export async function deleteActivityPackContainerItem(activityId: string, containerId: string, itemId: string): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}`)
}

/** Stufe Gepackt → Am Event: alle Positionen im Behälter ausgeben */
export async function issueAllPackContainerItems(activityId: string, containerId: string): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-containers/${containerId}/issue-all`)
}

/** Stufe Am Event → Retour: alles aus dem Behälter retournieren */
export async function returnAllPackContainerItems(activityId: string, containerId: string): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-containers/${containerId}/return-all`)
}

/** Ausgabe des ganzen Behälters rückgängig (wieder «Gepackt») */
export async function unissueAllPackContainerItems(activityId: string, containerId: string): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-containers/${containerId}/unissue-all`)
}

