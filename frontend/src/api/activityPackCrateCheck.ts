import apiClient from '@/api/apiClient'

export type PackCrateCheckLineStatus =
  | 'ok'
  | 'replenish'
  | 'not_taken'
  | 'loss'
  | 'repair'
  | 'extra'
  | 'problem'

export interface PackCrateCheckLinePayload {
  line_key: string
  material_item_id?: string | null
  material_name?: string | null
  expected_qty?: number
  counted_qty?: number
  status: PackCrateCheckLineStatus
  missing_qty?: number | null
  note?: string | null
  replenish_qty?: number | null
}

export interface PackCrateCheckRequest {
  container_batch_id?: string | null
  result: 'ok' | 'incomplete'
  lines: PackCrateCheckLinePayload[]
}

export interface PackCrateCheckResponse {
  ok: boolean
  actions_applied?: Array<Record<string, unknown>>
  errors?: Array<{ line_key?: string; material_item_id?: string; error: string }>
}

export async function getPackCrateCheckLooseStock(
  activityId: string,
  packItemId: string,
  materialItemIds: string[],
): Promise<Record<string, number>> {
  if (materialItemIds.length === 0) return {}
  const { data } = await apiClient.get<{ loose_stock_by_material_id: Record<string, number> }>(
    `/api/activities/${encodeURIComponent(activityId)}/pack-items/${encodeURIComponent(packItemId)}/crate-check-stock`,
    { params: { material_item_ids: materialItemIds.join(',') } },
  )
  return data.loose_stock_by_material_id ?? {}
}

export async function postPackCrateCheck(
  activityId: string,
  packItemId: string,
  body: PackCrateCheckRequest,
): Promise<PackCrateCheckResponse> {
  const { data } = await apiClient.post<PackCrateCheckResponse>(
    `/api/activities/${encodeURIComponent(activityId)}/pack-items/${encodeURIComponent(packItemId)}/crate-check`,
    body,
  )
  return data
}
