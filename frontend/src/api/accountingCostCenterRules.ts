import apiClient from '@/api/apiClient'

export type AccountingCostCenterRule = {
  id: string
  department_id: string
  source_kind: string
  cost_center_id: string
  cost_center_name: string
  default_entry_type: string | null
  default_payment_method: string | null
  created_at: string
  updated_at: string
}

export type CostCenterRuleUpsertBody = {
  source_kind: string
  cost_center_id: string
  default_entry_type?: string | null
  default_payment_method?: string | null
}

export async function listCostCenterRules(departmentId: string): Promise<AccountingCostCenterRule[]> {
  const { data } = await apiClient.get<AccountingCostCenterRule[]>(
    `/api/departments/${departmentId}/accounting/cost-center-rules`,
  )
  return data
}

export async function upsertCostCenterRule(
  departmentId: string,
  body: CostCenterRuleUpsertBody,
): Promise<AccountingCostCenterRule> {
  const { data } = await apiClient.put<AccountingCostCenterRule>(
    `/api/departments/${departmentId}/accounting/cost-center-rules`,
    body,
  )
  return data
}

export async function deleteCostCenterRule(departmentId: string, id: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/accounting/cost-center-rules/${id}`)
}

/** Follow-up-Typen mit Regel-Unterstützung */
export const COST_CENTER_RULE_SOURCE_KINDS = [
  'batch',
  'activity_consumption',
  'activity_replenishment',
  'activity_rental',
  'activity_workshop',
] as const

export type CostCenterRuleSourceKind = (typeof COST_CENTER_RULE_SOURCE_KINDS)[number]
