import apiClient from './apiClient'

export type GrossanlassWishKind = 'material' | 'fahrzeug' | 'beides'

export interface CreateGrossanlassWishPayload {
  group_id?: string
  ressort_group_id?: string
  new_bauprojekt?: { name: string; parent_id: string }
  wish_kind?: GrossanlassWishKind
  label?: string
  quantity?: number
  location?: string
  valid_from?: string
  valid_to?: string
  timeframe_notes?: string | null
  notes?: string | null
  refine_wish_id?: string
  custom_values?: Record<string, unknown>
}

export interface GrossanlassWishLine {
  id: string
  round_id: string
  response_id?: string | null
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
  status: 'requested' | 'accepted' | string
  last_stage?: 'grob' | 'fein' | string
  created_by_user_id: string
  created_by_name?: string
  created_at: string
  updated_at: string
  custom_values?: Record<string, unknown>
}

export interface GrossanlassWishListResult {
  items: GrossanlassWishLine[]
  total: number
  page: number
  limit: number
  counts: { requested: number; accepted: number }
}

export interface GrossanlassWishListFilters {
  group_id?: string
  status?: string
  q?: string
  page?: number
  limit?: number
}

export async function getGrossanlassRoundWishes(
  departmentId: string,
  roundId: string,
  filters?: GrossanlassWishListFilters,
): Promise<GrossanlassWishLine[] | GrossanlassWishListResult> {
  const params: Record<string, string | number> = {}
  if (filters?.group_id) params.group_id = filters.group_id
  if (filters?.status) params.status = filters.status
  if (filters?.q) params.q = filters.q
  if (filters?.page) params.page = filters.page
  if (filters?.limit) params.limit = filters.limit

  const paginated = filters?.page !== undefined || filters?.limit !== undefined
    || filters?.status !== undefined || filters?.q !== undefined

  const response = await apiClient.get<GrossanlassWishLine[] | GrossanlassWishListResult>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/wishes`,
    { params: Object.keys(params).length ? params : undefined },
  )
  return response.data
}

export async function getGrossanlassRefineCandidates(
  departmentId: string,
  roundId: string,
): Promise<GrossanlassWishLine[]> {
  const response = await apiClient.get<GrossanlassWishLine[]>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/refine-candidates`,
  )
  return response.data
}

export async function createGrossanlassWish(
  departmentId: string,
  roundId: string,
  data: CreateGrossanlassWishPayload,
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
  data: Partial<CreateGrossanlassWishPayload>,
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

export async function acceptGrossanlassWish(
  departmentId: string,
  roundId: string,
  wishId: string,
): Promise<GrossanlassWishLine> {
  const response = await apiClient.post<GrossanlassWishLine>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/wishes/${wishId}/accept`,
  )
  return response.data
}

export async function getMyRessortWishes(departmentId: string): Promise<GrossanlassWishLine[]> {
  const response = await apiClient.get<GrossanlassWishLine[]>(
    `/api/departments/${departmentId}/grossanlass/mein-ressort/wishes`,
  )
  return response.data
}
