import apiClient from './apiClient'
import type { GrossanlassWishKind } from './grossanlassWishes'

export interface GrossanlassProcurementPoolWish {
  id: string
  round_id: string
  round_name: string
  group_id: string
  group_name: string
  wish_kind: GrossanlassWishKind
  label: string
  quantity: number
  location: string
  valid_from: string
  valid_to: string
  created_by_name: string
  created_at: string
}

export interface GrossanlassProcurementLine {
  id: string
  department_id: string
  group_id: string
  group_name: string
  wish_kind: GrossanlassWishKind
  label: string
  quantity: number
  location: string
  notes: string | null
  status: string
  wish_line_ids: string[]
  wish_count: number
  created_at: string
  updated_at: string
}

export interface GrossanlassBedarfOverview {
  pool: GrossanlassProcurementPoolWish[]
  lines: GrossanlassProcurementLine[]
}

export async function getGrossanlassBedarfOverview(departmentId: string): Promise<GrossanlassBedarfOverview> {
  const response = await apiClient.get<GrossanlassBedarfOverview>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/bedarf`,
  )
  return response.data
}

export async function createGrossanlassProcurementLine(
  departmentId: string,
  data: {
    wish_line_ids: string[]
    label?: string
    quantity?: number
    location?: string
    group_id?: string
    notes?: string | null
  },
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.post<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines`,
    data,
  )
  return response.data
}

export async function addWishesToGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
  data: {
    wish_line_ids: string[]
    label?: string
    quantity?: number
  },
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.post<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}/wishes`,
    data,
  )
  return response.data
}

export async function updateGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
  data: Partial<{
    label: string
    quantity: number
    location: string
    group_id: string
    notes: string | null
  }>,
): Promise<GrossanlassProcurementLine> {
  const response = await apiClient.put<GrossanlassProcurementLine>(
    `/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}`,
    data,
  )
  return response.data
}

export async function deleteGrossanlassProcurementLine(
  departmentId: string,
  lineId: string,
): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/beschaffung/lines/${lineId}`)
}
