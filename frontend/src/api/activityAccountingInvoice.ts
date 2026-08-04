import apiClient from '@/api/apiClient'

export type ActivityAccountingInvoiceLine = {
  kind: 'follow_up' | 'workshop_open' | 'consumption_item' | string
  expected: boolean
  follow_up_id?: string | null
  booking_id?: string | null
  ticket_id?: string | null
  ticket_status?: string | null
  source_kind?: string | null
  status?: string | null
  label?: string | null
  material_item_id?: string | null
  material_name?: string | null
  quantity?: number | null
  amount_chf?: string | null
  estimated?: boolean
}

export type ActivityAccountingInvoice = {
  activity_id: string
  activity_name: string
  activity_type: string
  activity_status: string
  is_external: boolean
  customer_label: string | null
  collection_note: 'cash' | 'invoice' | null
  collection_note_amount: number | null
  status: 'empty' | 'draft' | 'open' | 'paid' | 'blocked' | string
  total_chf: string
  estimated_open_chf: string
  pending_followup_count: number
  recorded_followup_count: number
  expected_workshop_count: number
  lines: ActivityAccountingInvoiceLine[]
}

export type ActivityAccountingInvoiceSummary = {
  activity_id: string
  activity_name: string
  activity_type: string
  is_external: boolean
  customer_label: string | null
  status: string
  total_chf: string
  estimated_open_chf: string
  line_count: number
  pending_followup_count: number
  expected_workshop_count: number
  collection_note: 'cash' | 'invoice' | null
}

export async function getActivityAccountingInvoice(
  activityId: string,
): Promise<ActivityAccountingInvoice> {
  const { data } = await apiClient.get<ActivityAccountingInvoice>(
    `/api/activities/${activityId}/accounting-invoice`,
  )
  return data
}

export async function listDepartmentActivityInvoices(
  departmentId: string,
): Promise<ActivityAccountingInvoiceSummary[]> {
  const { data } = await apiClient.get<{ items: ActivityAccountingInvoiceSummary[] }>(
    `/api/departments/${departmentId}/accounting/activity-invoices`,
  )
  return data.items || []
}
