import apiClient from './apiClient'

export type PackMoveStage = 'packed' | 'issued' | 'returned'

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
  quantityIssued: number
  quantityReturned: number
  conditionOut: string | null
  notes: string | null
  isFullyPacked: boolean
  isFullyIssued: boolean
  isFullyReturned: boolean
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
    quantityIssued: num(raw.quantity_issued),
    quantityReturned: num(raw.quantity_returned),
    conditionOut: raw.condition_out != null ? String(raw.condition_out) : null,
    notes: raw.notes != null ? String(raw.notes) : null,
    isFullyPacked: Boolean(raw.is_fully_packed),
    isFullyIssued: Boolean(raw.is_fully_issued),
    isFullyReturned: Boolean(raw.is_fully_returned),
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

export async function postMovePackItem(
  activityId: string,
  packItemId: string,
  body: { stage: PackMoveStage; quantity: number },
): Promise<ActivityPackItem> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/pack-items/${packItemId}/move`,
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
