import apiClient from '@/api/apiClient'

export type AccountingGroupCostRow = {
  group_id: string
  group_name: string
  total_chf: string
  open_chf: string
  booking_count: number
}

export type AccountingGroupCostsResponse = {
  year: number
  scope: 'full' | 'leader_limited'
  rows: AccountingGroupCostRow[]
  totals: {
    ist_chf: string
    open_chf: string
    booking_count: number
  }
}

export async function listGroupCosts(
  departmentId: string,
  year: number,
): Promise<AccountingGroupCostsResponse> {
  const { data } = await apiClient.get<AccountingGroupCostsResponse>(
    `/api/departments/${departmentId}/accounting/group-costs`,
    { params: { year: String(year) } },
  )
  return data
}
