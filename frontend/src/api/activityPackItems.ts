import apiClient from './apiClient'

export type PackMoveStage = 'packed' | 'transport_to' | 'at_event' | 'transport_back' | 'returned' | 'stored'

export interface ActivityPackItem {
  id: string
  activityId: string
  materialItemId: string
  materialName: string
  categoryName: string | null
  categoryId: string | null
  packSize: number | null
  packUnit: string | null
  quantityOrdered: number
  quantityPacked: number
  quantityTransportTo: number
  quantityIssued: number
  quantityTransportBack: number
  quantityReturned: number
  quantityStored: number
  quantityWet: number
  wetHung: boolean | null
  wetDryingStorageAddressId: string | null
  wetDryingRackId: string | null
  wetDryingSlotId: string | null
  wetDryingLocationLabel: string | null
  wetWorkshopTicketId: string | null
  conditionOut: string | null
  notes: string | null
  isFullyPacked: boolean
  isFullyIssued: boolean
  isFullyReturned: boolean
  isFullyStored: boolean
  packDifference: number | null
  issueDifference: number | null
  returnDifference: number | null
  packedAt: string | null
  isConsumable: boolean
  isJsMaterial: boolean
  externalSource: string | null
  /** Gestell (Regal) bzw. Fallback «Ort»-Freitext — für Packstufe Bestätigt→Gepackt */
  storageRackName: string | null
  storageSlotName: string | null
  storageAddressName: string | null
  /** physical | physical_combo | virtual_combo */
  materialType: string
  linkedContainerLabel: string | null
  linkedContainerBatchId: string | null
}

export interface PackProgress {
  totalItems: number
  packedItems: number
  totalOrdered: number
  totalPacked: number
  progressPercent: number
  isComplete: boolean
}

function num(v: unknown, fallback = 0): number {
  const n = typeof v === 'number' ? v : parseInt(String(v ?? ''), 10)
  return Number.isFinite(n) ? n : fallback
}

function mapPackItem(raw: Record<string, unknown>): ActivityPackItem {
  return {
    id: String(raw.id ?? ''),
    activityId: String(raw.activity_id ?? ''),
    materialItemId: String(raw.material_item_id ?? ''),
    materialName: String(raw.material_name ?? ''),
    categoryName: raw.category_name != null ? String(raw.category_name) : null,
    categoryId: raw.category_id != null ? String(raw.category_id) : null,
    packSize: raw.pack_size != null ? num(raw.pack_size) : null,
    packUnit: raw.pack_unit != null ? String(raw.pack_unit) : null,
    quantityOrdered: num(raw.quantity_ordered),
    quantityPacked: num(raw.quantity_packed),
    quantityTransportTo: num(raw.quantity_transport_to),
    quantityIssued: num(raw.quantity_issued),
    quantityTransportBack: num(raw.quantity_transport_back),
    quantityReturned: num(raw.quantity_returned),
    quantityStored: num(raw.quantity_stored),
    quantityWet: num(raw.quantity_wet),
    wetHung: raw.wet_hung == null ? null : Boolean(raw.wet_hung),
    wetDryingStorageAddressId:
      raw.wet_drying_storage_address_id != null && String(raw.wet_drying_storage_address_id).trim() !== ''
        ? String(raw.wet_drying_storage_address_id)
        : null,
    wetDryingRackId:
      raw.wet_drying_rack_id != null && String(raw.wet_drying_rack_id).trim() !== ''
        ? String(raw.wet_drying_rack_id)
        : null,
    wetDryingSlotId:
      raw.wet_drying_slot_id != null && String(raw.wet_drying_slot_id).trim() !== ''
        ? String(raw.wet_drying_slot_id)
        : null,
    wetDryingLocationLabel:
      raw.wet_drying_location_label != null && String(raw.wet_drying_location_label).trim() !== ''
        ? String(raw.wet_drying_location_label).trim()
        : null,
    wetWorkshopTicketId:
      raw.wet_workshop_ticket_id != null && String(raw.wet_workshop_ticket_id).trim() !== ''
        ? String(raw.wet_workshop_ticket_id)
        : null,
    conditionOut: raw.condition_out != null ? String(raw.condition_out) : null,
    notes: raw.notes != null ? String(raw.notes) : null,
    isFullyPacked: Boolean(raw.is_fully_packed),
    isFullyIssued: Boolean(raw.is_fully_issued),
    isFullyReturned: Boolean(raw.is_fully_returned),
    isFullyStored: Boolean(raw.is_fully_stored),
    packDifference: raw.pack_difference != null ? num(raw.pack_difference) : null,
    issueDifference: raw.issue_difference != null ? num(raw.issue_difference) : null,
    returnDifference: raw.return_difference != null ? num(raw.return_difference) : null,
    packedAt: raw.packed_at != null ? String(raw.packed_at) : null,
    isConsumable: Boolean(raw.is_consumable),
    isJsMaterial: Boolean(raw.is_js_material),
    externalSource: raw.external_source != null ? String(raw.external_source) : null,
    storageRackName:
      raw.storage_rack_name != null && String(raw.storage_rack_name).trim() !== ''
        ? String(raw.storage_rack_name).trim()
        : null,
    storageSlotName:
      raw.storage_slot_name != null && String(raw.storage_slot_name).trim() !== ''
        ? String(raw.storage_slot_name).trim()
        : null,
    storageAddressName:
      raw.storage_address_name != null && String(raw.storage_address_name).trim() !== ''
        ? String(raw.storage_address_name).trim()
        : null,
    materialType: raw.material_type != null ? String(raw.material_type) : 'physical',
    linkedContainerLabel:
      raw.linked_container_label != null && String(raw.linked_container_label).trim() !== ''
        ? String(raw.linked_container_label).trim()
        : null,
    linkedContainerBatchId:
      raw.linked_container_batch_id != null && String(raw.linked_container_batch_id).trim() !== ''
        ? String(raw.linked_container_batch_id).trim()
        : null,
  }
}

export async function getPackItems(activityId: string): Promise<ActivityPackItem[]> {
  const { data } = await apiClient.get<Record<string, unknown>[]>(`/api/activities/${activityId}/pack-items`)
  const list = Array.isArray(data) ? data : []
  return list.map((row) => mapPackItem(row as Record<string, unknown>))
}

export async function postInitPackItems(activityId: string): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-items/init`)
}

export async function getPackProgress(activityId: string): Promise<PackProgress> {
  const { data } = await apiClient.get<Record<string, unknown>>(`/api/activities/${activityId}/pack-progress`)
  const d = data || {}
  return {
    totalItems: num(d.total_items),
    packedItems: num(d.packed_items),
    totalOrdered: num(d.total_ordered),
    totalPacked: num(d.total_packed),
    progressPercent: num(d.progress_percent),
    isComplete: Boolean(d.is_complete),
  }
}

export type PackMoveSource = 'tap' | 'scan' | 'bulk'

export async function postMovePackItem(
  activityId: string,
  packItemId: string,
  body: { stage: PackMoveStage; quantity: number; source?: PackMoveSource; from_wet?: boolean },
): Promise<ActivityPackItem> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-items/${packItemId}/move`,
    body,
  )
  return mapPackItem((data || {}) as Record<string, unknown>)
}

export type PackItemWetDisposition = {
  quantity_wet: number
  wet_hung?: boolean | null
  wet_drying_storage_address_id?: string | null
  wet_drying_rack_id?: string | null
  wet_drying_slot_id?: string | null
  wet_drying_location_label?: string | null
}

export async function postPackItemWet(
  activityId: string,
  packItemId: string,
  data: PackItemWetDisposition,
): Promise<ActivityPackItem> {
  const { data: raw } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-items/${packItemId}/wet`,
    data,
  )
  return mapPackItem((raw || {}) as Record<string, unknown>)
}

export async function patchActivityPackItem(
  activityId: string,
  packItemId: string,
  body: Partial<{
    quantity_packed: number
    quantity_issued: number
    quantity_returned: number
    condition_out: string | null
    notes: string | null
  }>,
): Promise<ActivityPackItem> {
  const { data } = await apiClient.patch<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-items/${packItemId}`,
    body,
  )
  return mapPackItem((data || {}) as Record<string, unknown>)
}

export async function postMoveBackPackItem(
  activityId: string,
  packItemId: string,
  body: { stage: PackMoveStage; quantity: number },
): Promise<ActivityPackItem> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-items/${packItemId}/moveback`,
    body,
  )
  return mapPackItem((data || {}) as Record<string, unknown>)
}

export async function postMoveAllPackItems(activityId: string, stage: PackMoveStage): Promise<void> {
  await apiClient.post(`/api/activities/${activityId}/pack-items/move-all`, { stage })
}
