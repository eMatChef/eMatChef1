import apiClient from '@/api/apiClient'

export type AccountingCostCenter = {
  id: string
  department_id: string
  name: string
  account_code: string | null
  description: string | null
  sort_order: number
  created_at: string
  updated_at: string
}

export async function listCostCenters(departmentId: string): Promise<AccountingCostCenter[]> {
  const { data } = await apiClient.get<AccountingCostCenter[]>(
    `/api/departments/${departmentId}/accounting/cost-centers`
  )
  return data
}

export async function createCostCenter(
  departmentId: string,
  body: { name: string; account_code?: string | null; description?: string | null; sort_order?: number }
): Promise<AccountingCostCenter> {
  const { data } = await apiClient.post<AccountingCostCenter>(
    `/api/departments/${departmentId}/accounting/cost-centers`,
    body
  )
  return data
}

export async function updateCostCenter(
  departmentId: string,
  id: string,
  body: Partial<Pick<AccountingCostCenter, 'name' | 'account_code' | 'description' | 'sort_order'>>
): Promise<AccountingCostCenter> {
  const { data } = await apiClient.patch<AccountingCostCenter>(
    `/api/departments/${departmentId}/accounting/cost-centers/${id}`,
    body
  )
  return data
}

export async function deleteCostCenter(departmentId: string, id: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/accounting/cost-centers/${id}`)
}

export type AccountingCostCenterBootstrapResult = {
  cost_centers_created: number
  rules_created: number
}

/** Idempotent: fehlende Standard-Kostenstellen und Zuordnungsregeln anlegen. */
export async function bootstrapCostCenters(
  departmentId: string,
): Promise<AccountingCostCenterBootstrapResult> {
  const { data } = await apiClient.post<AccountingCostCenterBootstrapResult>(
    `/api/departments/${departmentId}/accounting/cost-centers/bootstrap`,
  )
  return data
}
