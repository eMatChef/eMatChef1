import apiClient from './apiClient'

export interface ActivityPackContainer {
  id: string
  activity_id: string
  container_batch_id: string | null
  /** Material-ID der zugeordneten Kisten-Charge (wenn container_batch_id gesetzt) */
  container_material_item_id?: string | null
  /** Seriennummer der Lager-Charge (nicht Materialstamm) */
  container_serial_number?: string | null
  container_batch_label?: string | null
  container_storage_rack_name?: string | null
  container_storage_slot_name?: string | null
  /** Virtuelle Kombo (pack_mode together): Eltern-activity_item.id */
  source_activity_item_id?: string | null
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
  quantity_wet?: number
  wet_hung?: boolean | null
  wet_drying_storage_address_id?: string | null
  wet_drying_rack_id?: string | null
  wet_drying_slot_id?: string | null
  wet_drying_location_label?: string | null
  wet_workshop_ticket_id?: string | null
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
    quantity_wet: raw.quantity_wet != null ? num(raw.quantity_wet) : undefined,
    wet_hung: raw.wet_hung == null ? null : Boolean(raw.wet_hung),
    wet_drying_storage_address_id:
      raw.wet_drying_storage_address_id != null && String(raw.wet_drying_storage_address_id).trim() !== ''
        ? String(raw.wet_drying_storage_address_id)
        : null,
    wet_drying_rack_id:
      raw.wet_drying_rack_id != null && String(raw.wet_drying_rack_id).trim() !== ''
        ? String(raw.wet_drying_rack_id)
        : null,
    wet_drying_slot_id:
      raw.wet_drying_slot_id != null && String(raw.wet_drying_slot_id).trim() !== ''
        ? String(raw.wet_drying_slot_id)
        : null,
    wet_drying_location_label:
      raw.wet_drying_location_label != null && String(raw.wet_drying_location_label).trim() !== ''
        ? String(raw.wet_drying_location_label).trim()
        : null,
    wet_workshop_ticket_id:
      raw.wet_workshop_ticket_id != null && String(raw.wet_workshop_ticket_id).trim() !== ''
        ? String(raw.wet_workshop_ticket_id)
        : null,
    condition_out: String(raw.condition_out ?? 'ok'),
    notes: raw.notes != null ? String(raw.notes) : null,
    material_name: raw.material_name != null ? String(raw.material_name) : undefined,
    serial_number: raw.serial_number != null ? String(raw.serial_number) : null,
    batch_label: raw.batch_label != null ? String(raw.batch_label) : null,
  }
}

export type ContainerItemWetDisposition = {
  quantity_wet: number
  wet_hung?: boolean | null
  wet_drying_storage_address_id?: string | null
  wet_drying_rack_id?: string | null
  wet_drying_slot_id?: string | null
  wet_drying_location_label?: string | null
}

export async function postContainerItemWet(
  activityId: string,
  containerId: string,
  itemId: string,
  data: ContainerItemWetDisposition,
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}/wet`,
    data,
  )
  return mapPackContainerItem(response.data)
}

export async function postContainerItemStoreFromWet(
  activityId: string,
  containerId: string,
  itemId: string,
  quantity: number,
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}/store-from-wet`,
    { quantity },
  )
  return mapPackContainerItem(response.data)
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
  const response = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items`,
    data,
  )
  return mapPackContainerItem(response.data)
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
    quantity_transport_back?: number
    condition_out: string
    notes: string | null
  }>
): Promise<ActivityPackContainerItem> {
  const response = await apiClient.patch<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}`,
    data
  )
  return mapPackContainerItem(response.data)
}

export async function deleteActivityPackContainerItem(activityId: string, containerId: string, itemId: string): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/pack-containers/${containerId}/items/${itemId}`)
}

/** Behälter komplett zur nächsten Pipeline-Stufe buchen (stage = aktiver Tab). */
export async function issueAllPackContainerItems(
  activityId: string,
  containerId: string,
  stage?: import('@/api/activityPackItems').PackMoveStage,
  source?: import('@/api/activityPackItems').PackMoveSource,
): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-containers/${containerId}/issue-all`, {
    ...(stage ? { stage } : {}),
    ...(source ? { source } : {}),
  })
}

/** Stufe Am Event → Retour: alles aus dem Behälter retournieren */
export async function returnAllPackContainerItems(
  activityId: string,
  containerId: string,
  source?: import('@/api/activityPackItems').PackMoveSource,
): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-containers/${containerId}/return-all`, {
    ...(source ? { source } : {}),
  })
}

/** Buchung des ganzen Behälters um eine Stufe zurück (stage = aktiver Tab). */
export async function unissueAllPackContainerItems(
  activityId: string,
  containerId: string,
  stage?: import('@/api/activityPackItems').PackMoveStage,
): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-containers/${containerId}/unissue-all`, {
    ...(stage ? { stage } : {}),
  })
}

