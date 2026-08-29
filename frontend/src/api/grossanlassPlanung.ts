import apiClient from './apiClient'

export type GrossanlassStrukturModus = 'offen' | 'verschachtelt' | 'parallel'
export type GrossanlassPhaseRole = 'anlass' | 'aufbau' | 'abbau' | 'vorevent' | 'nach_event'

export type GrossanlassGuestActivityType = 'camp' | 'event'

export type GrossanlassPlanungConfig = {
  status: string
  struktur_modus: GrossanlassStrukturModus
  planned_event_start: string
  planned_event_end: string | null
  main_activity_id: string | null
  location_text: string
  venue_address_id: string | null
  notes: string
  published_at: string | null
  guest_activity_type: GrossanlassGuestActivityType
  has_guest_departments: boolean
  invite_group_ids: string[]
  logistics_group_id?: string | null
}

export type GrossanlassPlanungActivity = {
  id: string
  name: string
  role: GrossanlassPhaseRole | string
  status: string
  usage_start: string | null
  usage_end: string | null
}

export type GrossanlassParticipantStatus = 'planned' | 'pending' | 'accepted' | 'rejected'

export type GrossanlassParticipant = {
  id: string
  department_id: string
  name: string
  organisation_name: string
  parent_id: string | null
  status: GrossanlassParticipantStatus
  guest_activity_id: string | null
  unterlager_id: string | null
}

export type GrossanlassPlanungRessortMember = {
  name: string
  is_leader: boolean
}

export type GrossanlassPlanungRessort = {
  id: string
  name: string
  node_type: string
  kind?: string
  parent_id: string | null
  member_count: number
  members?: GrossanlassPlanungRessortMember[]
}

export type GrossanlassUnterlager = {
  id: string
  name: string
  parent_id: string | null
  sort_order: number
}

export type GrossanlassPlanungOverview = {
  config: GrossanlassPlanungConfig
  department_name: string
  checks: { period: boolean; ressorts: boolean; participants: boolean }
  activities: GrossanlassPlanungActivity[]
  ressorts: GrossanlassPlanungRessort[]
  unterlager: GrossanlassUnterlager[]
  participants: GrossanlassParticipant[]
  can_manage: boolean
}

export type GrossanlassGuestSearchHit = {
  id: string
  name: string
  organisation_name: string
  parent_id: string | null
}

export async function getGrossanlassPlanung(departmentId: string): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.get<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung`,
  )
  return response.data
}

export async function updateGrossanlassPlanung(
  departmentId: string,
  data: {
    struktur_modus?: GrossanlassStrukturModus
    location_text?: string
    venue_address_id?: string | null
    notes?: string
    planned_event_start?: string
    planned_event_end?: string | null
    guest_activity_type?: GrossanlassGuestActivityType
    has_guest_departments?: boolean
    invite_group_ids?: string[]
    department_name?: string
    logistics_group_id?: string | null
  },
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.patch<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung`,
    data,
  )
  return response.data
}

export async function createGrossanlassPhaseActivity(
  departmentId: string,
  data: { role: GrossanlassPhaseRole; name?: string; usage_start?: string; usage_end?: string },
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.post<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/activities`,
    data,
  )
  return response.data
}

export async function publishGrossanlass(departmentId: string): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.post<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/publish`,
  )
  return response.data
}

export async function searchGrossanlassGuests(
  departmentId: string,
  q: string,
): Promise<GrossanlassGuestSearchHit[]> {
  const response = await apiClient.get<GrossanlassGuestSearchHit[]>(
    `/api/departments/${departmentId}/grossanlass/planung/participants/search`,
    { params: { q } },
  )
  return response.data
}

export async function addGrossanlassParticipant(
  departmentId: string,
  guestDepartmentId: string,
  unterlagerId?: string | null,
): Promise<GrossanlassPlanungOverview> {
  const body: { guest_department_id: string; unterlager_id?: string } = {
    guest_department_id: guestDepartmentId,
  }
  if (unterlagerId) body.unterlager_id = unterlagerId
  const response = await apiClient.post<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/participants`,
    body,
  )
  return response.data
}

export async function updateGrossanlassParticipant(
  departmentId: string,
  participantId: string,
  data: { unterlager_id: string | null },
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.patch<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/participants/${participantId}`,
    data,
  )
  return response.data
}

export async function createGrossanlassUnterlager(
  departmentId: string,
  data: { name: string; parent_id?: string | null },
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.post<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/unterlager`,
    data,
  )
  return response.data
}

export async function updateGrossanlassUnterlager(
  departmentId: string,
  unterlagerId: string,
  data: { name?: string; parent_id?: string | null },
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.patch<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/unterlager/${unterlagerId}`,
    data,
  )
  return response.data
}

export async function removeGrossanlassUnterlager(
  departmentId: string,
  unterlagerId: string,
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.delete<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/unterlager/${unterlagerId}`,
  )
  return response.data
}

export async function removeGrossanlassParticipant(
  departmentId: string,
  participantId: string,
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.delete<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/participants/${participantId}`,
  )
  return response.data
}

export async function respondGrossanlassInvite(payload: {
  guestDepartmentId: string
  participantId: string
  decision: 'accepted' | 'rejected'
  groupId?: string
}): Promise<{ ok: boolean; guest_activity_id: string | null }> {
  const body: Record<string, string> = { decision: payload.decision }
  if (payload.decision === 'accepted' && payload.groupId) {
    body.group_id = payload.groupId
  }
  const response = await apiClient.post<{ ok: boolean; guest_activity_id: string | null }>(
    `/api/departments/${payload.guestDepartmentId}/grossanlass/invites/${payload.participantId}/respond`,
    body,
  )
  return response.data
}
