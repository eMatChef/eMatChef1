import apiClient from '@/api/apiClient'

export type AccountingOverviewYearRow = {
  year: number
  total_chf: string
  booking_count: number
}

export type AccountingOverviewCostCenterRow = {
  cost_center_id: string
  name: string
  total_chf: string
  booking_count: number
}

export type AccountingOverviewEntryTypeRow = {
  entry_type: string
  total_chf: string
  booking_count: number
}

export type AccountingOverview = {
  years: AccountingOverviewYearRow[]
  selected_year: number
  selected_year_total_chf: string
  selected_year_booking_count: number
  by_cost_center: AccountingOverviewCostCenterRow[]
  by_entry_type: AccountingOverviewEntryTypeRow[]
  pending_followup_count: number
  cost_center_count: number
}

export async function getAccountingOverview(
  departmentId: string,
  year?: number
): Promise<AccountingOverview> {
  const params: Record<string, string> = {}
  if (year !== undefined && Number.isFinite(year)) {
    params.year = String(year)
  }
  const { data } = await apiClient.get<AccountingOverview>(
    `/api/departments/${departmentId}/accounting/overview`,
    { params }
  )
  return data
}
