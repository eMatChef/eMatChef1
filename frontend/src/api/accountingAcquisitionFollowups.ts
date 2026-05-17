import apiClient from '@/api/apiClient'

export type AccountingAcquisitionFollowUp = {
  id: string
  department_id: string
  material_batch_id: string | null
  activity_id?: string | null
  activity_name?: string | null
  activity_group_id?: string | null
  activity_type?: string | null
  source_kind?: string | null
  source_ref_id?: string | null
  material_item_id?: string | null
  material_name?: string | null
  amount: string
  suggested_date: string
  receipt_label: string | null
  status: 'pending' | 'recorded'
  accounting_booking_id: string | null
  created_at: string
  updated_at: string
}

export type AcquisitionFollowUpCreateBody = {
  amount: string | number
  suggested_date: string
  receipt_label?: string | null
  material_batch_id?: string | null
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
