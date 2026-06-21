import apiClient from './apiClient'

export type GrossanlassWishKind = 'material' | 'fahrzeug' | 'beides'

export interface GrossanlassWishLine {
  id: string
  round_id: string
  group_id: string
  group_name: string
  wish_kind: GrossanlassWishKind
  label: string
  quantity: number
  location: string
  valid_from: string
  valid_to: string
  timeframe_notes: string | null
  notes: string | null
  status: string
  created_by_user_id: string
  created_at: string
  updated_at: string
}

export async function getGrossanlassRoundWishes(
  departmentId: string,
  roundId: string,
  groupId?: string,
): Promise<GrossanlassWishLine[]> {
  const params = groupId ? { group_id: groupId } : undefined
  const response = await apiClient.get<GrossanlassWishLine[]>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/wishes`,
    { params },
  )
  return response.data
}

export async function createGrossanlassWish(
  departmentId: string,
  roundId: string,
  data: {
    group_id?: string
    new_bauprojekt?: { name: string; parent_id: string }
    wish_kind: GrossanlassWishKind
    label: string
    quantity: number
    location: string
    valid_from: string
    valid_to: string
    timeframe_notes?: string | null
    notes?: string | null
  },
): Promise<GrossanlassWishLine> {
  const response = await apiClient.post<GrossanlassWishLine>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/wishes`,
    data,
  )
  return response.data
}

export async function updateGrossanlassWish(
  departmentId: string,
  roundId: string,
  wishId: string,
  data: Partial<{
    wish_kind: GrossanlassWishKind
    label: string
    quantity: number
    location: string
    valid_from: string
    valid_to: string
    timeframe_notes: string | null
    notes: string | null
  }>,
): Promise<GrossanlassWishLine> {
  const response = await apiClient.put<GrossanlassWishLine>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/wishes/${wishId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassWish(
  departmentId: string,
  roundId: string,
  wishId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/wishes/${wishId}`,
  )
}

export async function getMyRessortWishes(departmentId: string): Promise<GrossanlassWishLine[]> {
  const response = await apiClient.get<GrossanlassWishLine[]>(
    `/api/departments/${departmentId}/grossanlass/mein-ressort/wishes`,
  )
  return response.data
}
