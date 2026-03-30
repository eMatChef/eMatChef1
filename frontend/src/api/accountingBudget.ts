import apiClient from '@/api/apiClient'

export type BudgetComparisonRow = {
  cost_center_id: string
  cost_center_name: string
  sort_order: number
  budget_line_id: string | null
  budget_amount_chf: string | null
  budget_notes: string | null
  ist_amount_chf: string
  remaining_chf: string | null
  booking_count: number
}

export type BudgetComparisonTotals = {
  ist_chf: string
  budget_chf: string | null
  remaining_chf: string | null
}

export type BudgetComparison = {
  year: number
  rows: BudgetComparisonRow[]
  totals: BudgetComparisonTotals
}

export async function getBudgetComparison(
  departmentId: string,
  year: number
): Promise<BudgetComparison> {
  const { data } = await apiClient.get<BudgetComparison>(
    `/api/departments/${departmentId}/accounting/budget/comparison/${year}`
  )
  return data
}

export async function downloadBudgetCsv(departmentId: string, year: number): Promise<void> {
  const { data } = await apiClient.get<Blob>(
    `/api/departments/${departmentId}/accounting/budget/comparison/${year}`,
    { params: { format: 'csv' }, responseType: 'blob' }
  )
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = `budget-ist-${departmentId}-${year}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

export type AccountingBudgetLine = {
  id: string
  department_id: string
  cost_center_id: string
  cost_center_name: string
  calendar_year: number
  amount_chf: string
  notes: string | null
  created_at: string
  updated_at: string
}

export async function createBudgetLine(
  departmentId: string,
  body: {
    cost_center_id: string
    calendar_year: number
    amount_chf: string | number
    notes?: string | null
  }
): Promise<AccountingBudgetLine> {
  const { data } = await apiClient.post<AccountingBudgetLine>(
    `/api/departments/${departmentId}/accounting/budget/lines`,
    body
  )
  return data
}

export async function updateBudgetLine(
  departmentId: string,
  lineId: string,
  body: { amount_chf?: string | number; notes?: string | null }
): Promise<AccountingBudgetLine> {
  const { data } = await apiClient.patch<AccountingBudgetLine>(
    `/api/departments/${departmentId}/accounting/budget/lines/${lineId}`,
    body
  )
  return data
}

export async function deleteBudgetLine(departmentId: string, lineId: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/accounting/budget/lines/${lineId}`)
}
