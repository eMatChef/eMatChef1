import apiClient from '@/api/apiClient'

export type AccountingChargeTarget = 'group' | 'department' | 'external_customer'

export type AccountingAcquisitionFollowUp = {
  id: string
  department_id: string
  department_name?: string | null
  material_batch_id: string | null
  activity_id?: string | null
  activity_name?: string | null
  activity_group_id?: string | null
  activity_type?: string | null
  source_kind?: string | null
  source_ref_id?: string | null
  material_item_id?: string | null
  material_name?: string | null
  material_department_id?: string | null
  material_department_name?: string | null
  amount: string
  suggested_date: string
  receipt_label: string | null
  status: 'pending' | 'recorded'
  accounting_booking_id: string | null
  created_at: string
  updated_at: string
  charge_target?: AccountingChargeTarget | null
  suggested_group_id?: string | null
  external_customer_label?: string | null
  reported_by_user_id?: string | null
  reported_by_display_name?: string | null
}

export type AcquisitionFollowUpCreateBody = {
  amount: string | number
  suggested_date: string
  receipt_label?: string | null
  material_batch_id?: string | null
}

export async function listActivityAcquisitionFollowups(
  activityId: string,
  status: 'pending' | 'recorded' = 'pending',
): Promise<AccountingAcquisitionFollowUp[]> {
  const { data } = await apiClient.get<AccountingAcquisitionFollowUp[]>(
    `/api/activities/${activityId}/accounting-followups`,
    { params: { status } },
  )
  return data
}

export async function listAcquisitionFollowups(
  departmentId: string,
  status: 'pending' | 'recorded' = 'pending',
  activityId?: string
): Promise<AccountingAcquisitionFollowUp[]> {
  const params: Record<string, string> = { status }
  if (activityId) params.activity_id = activityId
  const { data } = await apiClient.get<AccountingAcquisitionFollowUp[]>(
    `/api/departments/${departmentId}/accounting/acquisition-followups`,
    { params }
  )
  return data
}

export async function createAcquisitionFollowup(
  departmentId: string,
  body: AcquisitionFollowUpCreateBody
): Promise<AccountingAcquisitionFollowUp> {
  const { data } = await apiClient.post<AccountingAcquisitionFollowUp>(
    `/api/departments/${departmentId}/accounting/acquisition-followups`,
    body
  )
  return data
}

export type BatchRecordFollowUpsBody = {
  follow_up_ids: string[]
  cost_center_id: string
  entry_type: string
  payment_method?: string | null
  payment_status?: string | null
  group_id?: string | null
  notes?: string | null
}

export async function batchRecordFollowUps(
  departmentId: string,
  body: BatchRecordFollowUpsBody,
): Promise<{ recorded: Array<{ booking_id: string; follow_up_id: string }>; count: number }> {
  const { data } = await apiClient.post<{ recorded: Array<{ booking_id: string; follow_up_id: string }>; count: number }>(
    `/api/departments/${departmentId}/accounting/acquisition-followups/batch-record`,
    body,
  )
  return data
}
