import apiClient from './apiClient'

// ============== Types ==============

export interface WorkshopMaterialInfo {
  id: string
  name: string
  condition: string
  barcode_tag: string | null
  sale_price?: string | null
  category: {
    id: string
    name: string
  } | null
}

export interface WorkshopUserInfo {
  id: string
  name: string
}

export interface WorkshopActivityInfo {
  id: string
  name: string
  type: string
  status: string
}

export interface WorkshopIssueReportInfo {
  id: string
  type: string
  type_label: string
  description: string | null
  quantity: number | null
  photo_url: string | null
  reported_at: string
  reported_by: WorkshopUserInfo | null
  resolved: boolean
  resolved_at: string | null
}

export type TicketType = 'repair' | 'inspection' | 'writeoff' | 'cleaning'
export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent'
export type TicketStatus = 'open' | 'in_progress' | 'waiting_parts' | 'completed' | 'cancelled'

export interface WorkshopTicket {
  id: string
  department_id: string
  type: TicketType
  type_label: string
  priority: TicketPriority
  priority_label: string
  status: TicketStatus
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
  material_item: WorkshopMaterialInfo
  assigned_to: WorkshopUserInfo | null
  assigned_to_supplier_company?: { id: string; name: string } | null
  created_by: WorkshopUserInfo | null
  activity_id: string | null
  activity_type?: string | null
  issue_report_id: string | null
  origin_source?: 'issue_report' | 'manual'
  origin_issue_type?: 'repair' | 'loss' | 'damage' | 'consumption' | null
  origin_issue_type_label?: string | null

  public_code?: string | null
  public_url?: string | null

  // Detail-Felder (nur bei get mit details)
  parts_used?: any[] | null
  photos?: string[] | null
  activity?: WorkshopActivityInfo | null
  issue_report?: WorkshopIssueReportInfo | null
  allowed_transitions?: TicketStatus[]
}

export interface WorkshopStats {
  status_counts: Record<TicketStatus, number>
  completed_this_week: number
  type_counts: Record<TicketType, number>
  priority_counts: Record<TicketPriority, number>
  total_active: number
  pending_cost_tasks?: {
    waiting_quote: number
    missing_estimated_cost: number
  }
}

export interface CreateTicketRequest {
  department_id: string
  material_item_id: string
  title: string
  type?: TicketType
  priority?: TicketPriority
  description?: string | null
  activity_id?: string | null
  issue_report_id?: string | null
  assigned_to_user_id?: string | null
  estimated_cost?: string | null
}

export interface UpdateTicketRequest {
  title?: string
  description?: string | null
  type?: TicketType
  priority?: TicketPriority
  assigned_to_user_id?: string | null
  assigned_to_supplier_company_id?: string | null
  estimated_cost?: string | null
  actual_cost?: string | null
  parts_used?: any[] | null
  photos?: string[] | null
  resolution_notes?: string | null
}

export interface TransitionRequest {
  status: TicketStatus
  resolution_action?: 'repaired' | 'writeoff' | 'ok'
  resolution_notes?: string
  actual_cost?: string | null
  estimated_cost?: string | null
  writeoff_qty?: number
}

export interface WorkshopHistoryEntry {
  id: string
  action: string
  action_label: string
  changes: Record<string, any>
  user: WorkshopUserInfo | null
  created_at: string
}

// ============== API Functions ==============

/**
 * Lädt alle Workshop-Tickets für ein Department
 */
export async function getWorkshopTickets(
  departmentId: string,
  options?: {
    status?: TicketStatus
    type?: TicketType
    priority?: TicketPriority
    search?: string
    assigned_to?: string
    activity_id?: string
    /** Nur Tickets zu diesem Material (z. B. Material-Detail) */
    material_item_id?: string
  }
): Promise<WorkshopTicket[]> {
  const params = new URLSearchParams({ department_id: departmentId })

  if (options?.status) params.append('status', options.status)
  if (options?.type) params.append('type', options.type)
  if (options?.priority) params.append('priority', options.priority)
  if (options?.search) params.append('search', options.search)
  if (options?.assigned_to) params.append('assigned_to', options.assigned_to)
  if (options?.activity_id) params.append('activity_id', options.activity_id)
  if (options?.material_item_id) params.append('material_item_id', options.material_item_id)

  const response = await apiClient.get<WorkshopTicket[]>(`/api/workshop?${params.toString()}`)
  return response.data
}

/**
 * Lädt ein einzelnes Ticket mit Details
 */
export async function getWorkshopTicket(id: string): Promise<WorkshopTicket> {
  const response = await apiClient.get<WorkshopTicket>(`/api/workshop/${id}`)
  return response.data
}

export async function ensureWorkshopPublicCode(ticketId: string): Promise<WorkshopTicket> {
  const { data } = await apiClient.post<WorkshopTicket>(`/api/workshop/${ticketId}/public-code`)
  return data
}

/**
 * Erstellt ein neues Workshop-Ticket
 */
export async function createWorkshopTicket(data: CreateTicketRequest): Promise<WorkshopTicket> {
  const response = await apiClient.post<WorkshopTicket>('/api/workshop', data)
  return response.data
}

/**
 * Aktualisiert ein Ticket
 */
export async function updateWorkshopTicket(id: string, data: UpdateTicketRequest): Promise<WorkshopTicket> {
  const response = await apiClient.patch<WorkshopTicket>(`/api/workshop/${id}`, data)
  return response.data
}

/**
 * Führt einen Status-Übergang durch
 */
export async function transitionWorkshopTicket(id: string, data: TransitionRequest): Promise<WorkshopTicket> {
  const response = await apiClient.post<WorkshopTicket>(`/api/workshop/${id}/transition`, data)
  return response.data
}

/**
 * Löscht ein Ticket
 */
export async function deleteWorkshopTicket(id: string): Promise<void> {
  await apiClient.delete(`/api/workshop/${id}`)
}

/**
 * Lädt Workshop-Statistiken
 */
export async function getWorkshopStats(departmentId: string): Promise<WorkshopStats> {
  const response = await apiClient.get<WorkshopStats>(`/api/workshop/stats?department_id=${departmentId}`)
  return response.data
}

/**
 * Lädt die History eines Tickets (chronologisch)
 */
export async function getWorkshopTicketHistory(ticketId: string): Promise<WorkshopHistoryEntry[]> {
  const response = await apiClient.get<WorkshopHistoryEntry[]>(`/api/workshop/${ticketId}/history`)
  return response.data
}
