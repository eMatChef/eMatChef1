import apiClient from './apiClient'

export type ReplenishmentWishStatus = 'pending' | 'fulfilled' | 'rejected' | 'cancelled'

export interface ReplenishmentWishAvailabilitySnapshot {
  available_for_period?: number
  total_stock?: number
  rack_label?: string | null
  [key: string]: unknown
}

export interface ActivityReplenishmentWish {
  id: string
  activityId: string
  materialItemId: string
  materialName: string
  quantityRequested: number
  notes: string | null
  status: ReplenishmentWishStatus
  requestedByUserId: string
  requestedByName: string | null
  requestedAt: string
  decidedByUserId: string | null
  decidedAt: string | null
  rejectionReason: string | null
  fulfilledActivityItemId: string | null
  availabilitySnapshot: ReplenishmentWishAvailabilitySnapshot | null
}

function mapWish(raw: Record<string, unknown>): ActivityReplenishmentWish {
  const snapshot = raw.availability_snapshot
  return {
    id: String(raw.id ?? ''),
    activityId: String(raw.activity_id ?? ''),
    materialItemId: String(raw.material_item_id ?? ''),
    materialName: String(raw.material_name ?? ''),
    quantityRequested: Number(raw.quantity_requested ?? 0),
    notes: raw.notes != null ? String(raw.notes) : null,
    status: String(raw.status ?? 'pending') as ReplenishmentWishStatus,
    requestedByUserId: String(raw.requested_by_user_id ?? ''),
    requestedByName: raw.requested_by_name != null ? String(raw.requested_by_name) : null,
    requestedAt: String(raw.requested_at ?? ''),
    decidedByUserId: raw.decided_by_user_id != null ? String(raw.decided_by_user_id) : null,
    decidedAt: raw.decided_at != null ? String(raw.decided_at) : null,
    rejectionReason: raw.rejection_reason != null ? String(raw.rejection_reason) : null,
    fulfilledActivityItemId:
      raw.fulfilled_activity_item_id != null ? String(raw.fulfilled_activity_item_id) : null,
    availabilitySnapshot:
      snapshot != null && typeof snapshot === 'object'
        ? (snapshot as ReplenishmentWishAvailabilitySnapshot)
        : null,
  }
}

export async function getReplenishmentWishes(
  activityId: string,
  params?: { status?: string },
): Promise<ActivityReplenishmentWish[]> {
  const { data } = await apiClient.get<Record<string, unknown>[]>(
    `/api/activities/${activityId}/replenishment-wishes`,
    { params },
  )
  const list = Array.isArray(data) ? data : []
  return list.map((row) => mapWish(row as Record<string, unknown>))
}

export async function postReplenishmentWish(
  activityId: string,
  body: {
    material_item_id: string
    quantity: number
    notes?: string | null
    availability_snapshot?: ReplenishmentWishAvailabilitySnapshot | null
  },
): Promise<ActivityReplenishmentWish> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/replenishment-wishes`,
    body,
  )
  return mapWish((data ?? {}) as Record<string, unknown>)
}

export async function patchReplenishmentWish(
  activityId: string,
  wishId: string,
  body: { action: 'cancel' | 'reject'; reason?: string },
): Promise<ActivityReplenishmentWish> {
  const { data } = await apiClient.patch<Record<string, unknown>>(
    `/api/activities/${activityId}/replenishment-wishes/${wishId}`,
    body,
  )
  return mapWish((data ?? {}) as Record<string, unknown>)
}

export async function postFulfillReplenishmentWish(
  activityId: string,
  wishId: string,
): Promise<ActivityReplenishmentWish> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/replenishment-wishes/${wishId}/fulfill`,
  )
  return mapWish((data ?? {}) as Record<string, unknown>)
}
