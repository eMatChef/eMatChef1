import apiClient from '@/api/apiClient'

export type MaterialCostRow = {
  material_id: string
  material_name: string
  total_chf: string
  booking_count: number
}

export type MaterialCostsResponse = {
  year: number
  rows: MaterialCostRow[]
  totals: {
    total_chf: string
    booking_count: number
  }
}

export async function getMaterialCosts(
  departmentId: string,
  year: number
): Promise<MaterialCostsResponse> {
  const { data } = await apiClient.get<MaterialCostsResponse>(
    `/api/departments/${departmentId}/accounting/material-costs`,
    { params: { year: String(year) } }
  )
  return data
}
