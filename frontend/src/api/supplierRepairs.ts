import apiClient from './apiClient'

export type SupplierRepairStatus = 'open' | 'in_progress' | 'waiting_parts' | 'completed' | 'cancelled'

export interface SupplierRepairTicket {
  id: string
  type: string
  type_label: string
  priority: string
  priority_label: string
  status: SupplierRepairStatus
  status_label: string
  title: string
  description: string | null
  estimated_cost: string | null
  actual_cost: string | null
  resolution_action: string | null
  resolution_notes: string | null
  started_at: string | null
  completed_at: string | null
  created_at: string
  updated_at: string
  allowed_transitions: SupplierRepairStatus[]
  material_item: {
    id: string
    name: string
    condition: string
    serial_number: string | null
  }
  department: {
    id: string
    name: string
  }
  issue_report?: {
    type: string
    type_label: string
    description: string | null
    photo_url: string | null
    reported_at: string
  } | null
  photos?: string[] | null
  department_contact?: { name: string } | null
}

export interface SupplierRepairTransitionPayload {
  status: SupplierRepairStatus
  estimated_cost?: string | null
  actual_cost?: string | null
  resolution_action?: string
  resolution_notes?: string | null
}

export async function listSupplierRepairs(
  companyId: string,
  status?: SupplierRepairStatus,
): Promise<SupplierRepairTicket[]> {
  const { data } = await apiClient.get<{ tickets: SupplierRepairTicket[] }>(
    `/api/supplier-companies/${companyId}/repairs`,
    { params: status ? { status } : undefined },
  )
  return data.tickets
}

export async function getSupplierRepair(
  companyId: string,
  ticketId: string,
): Promise<SupplierRepairTicket> {
  const { data } = await apiClient.get<{ ticket: SupplierRepairTicket }>(
    `/api/supplier-companies/${companyId}/repairs/${ticketId}`,
  )
  return data.ticket
}

export async function updateSupplierRepair(
  companyId: string,
  ticketId: string,
  payload: Partial<{
    estimated_cost: string | null
    actual_cost: string | null
    photos: string[]
    resolution_notes: string | null
  }>,
): Promise<SupplierRepairTicket> {
  const { data } = await apiClient.patch<{ ticket: SupplierRepairTicket }>(
    `/api/supplier-companies/${companyId}/repairs/${ticketId}`,
    payload,
  )
  return data.ticket
}

export async function transitionSupplierRepair(
  companyId: string,
  ticketId: string,
  payload: SupplierRepairTransitionPayload,
): Promise<SupplierRepairTicket> {
  const { data } = await apiClient.post<{ ticket: SupplierRepairTicket }>(
    `/api/supplier-companies/${companyId}/repairs/${ticketId}/transition`,
    payload,
  )
  return data.ticket
}
