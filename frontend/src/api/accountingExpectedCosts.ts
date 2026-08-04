import apiClient from '@/api/apiClient'

export type AccountingExpectedCostItem = {
  kind: 'workshop_open'
  ticket_id: string
  ticket_title: string
  ticket_status: 'open' | 'in_progress' | 'waiting_parts' | string
  activity_id: string | null
  activity_name: string | null
  material_item_id: string | null
  material_name: string | null
  estimated_cost_chf: string | null
  estimated_cost_is_estimate: boolean
  billing_department_id: string | null
  billing_department_name: string | null
}

export type AccountingExpectedCosts = {
  workshop_open_count: number
  workshop_open_activity_count: number
  items: AccountingExpectedCostItem[]
}

export async function listAccountingExpectedCosts(
  departmentId: string,
): Promise<AccountingExpectedCosts> {
  const { data } = await apiClient.get<AccountingExpectedCosts>(
    `/api/departments/${departmentId}/accounting/expected-costs`,
  )
  return data
}
