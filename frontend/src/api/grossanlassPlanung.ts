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
  notes: string
  published_at: string | null
  guest_activity_type: GrossanlassGuestActivityType
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
  status: GrossanlassParticipantStatus
  guest_activity_id: string | null
}

export type GrossanlassPlanungOverview = {
  config: GrossanlassPlanungConfig
  department_name: string
  checks: { period: boolean; ressorts: boolean; participants: boolean }
  activities: GrossanlassPlanungActivity[]
  ressorts: { id: string; name: string; node_type: string; member_count: number }[]
  storage_locations: { id: string; name: string; is_primary: boolean }[]
  participants: GrossanlassParticipant[]
  can_manage: boolean
}

export type GrossanlassGuestSearchHit = {
  id: string
  name: string
  organisation_name: string
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
    notes?: string
    planned_event_start?: string
    planned_event_end?: string | null
    guest_activity_type?: GrossanlassGuestActivityType
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
): Promise<GrossanlassPlanungOverview> {
  const response = await apiClient.post<GrossanlassPlanungOverview>(
    `/api/departments/${departmentId}/grossanlass/planung/participants`,
    { guest_department_id: guestDepartmentId },
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
