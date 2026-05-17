import apiClient from './apiClient'

export interface MyJoinRequest {
  id: string
  status: 'pending' | 'approved' | 'rejected'
  department_id: string
  department_name: string
  message?: string | null
  created_at: string
  updated_at: string
}

export interface DepartmentSearchResult {
  id: string
  name: string
  organisation_name: string
  /** Einladungs-/Antwortstatus (wenn aus Aktivität geladen) */
  status?: string
  /** Gast-Department: welche Gruppe an der Aktivität teilnimmt (optional) */
  group_id?: string | null
  group_name?: string | null
}

export interface PendingJoinRequest {
  id: string
  user_id: string
  name: string
  email?: string | null
  message?: string | null
  created_at: string
}

export interface DepartmentInviteData {
  department_id: string
  department_name: string
  join_code: string
  invite_url: string
  qr_payload: string
  /** Organisation des Departments (für Registrierungs-Einladung) */
  organisation_id?: string
  /** Login mit Registrierung: Organisation + Abteilung vorausgefüllt, danach Redirect zur Join-Seite */
  register_invite_url?: string
  register_qr_payload?: string
  updated_at: string
}

export interface PendingInvite {
  id: string
  email: string
  role: string
  status?: 'pending' | 'accepted' | 'declined'
  invite_url: string
  created_at: string
  created_by_user_id?: string
  created_by_name?: string
  user_registered?: boolean
  user_name?: string
  accepted_at?: string
  accepted_user_id?: string
  accepted_user_name?: string
}

export interface InviteAcceptedNotification {
  id: string
  type: 'invite_accepted'
  email: string
  user_id: string
  user_name: string
  invited_by_user_id: string
  invited_by_name: string
  role: string
  accepted_at: string
  read: boolean
}

export interface ReceivedDepartmentInviteNotification {
  id: string
  type: 'department_invite'
  invite_id: string
  department_id: string
  department_name: string
  invited_by_user_id: string
  invited_by_name: string
  invited_by_first_name?: string | null
  invited_by_last_name?: string | null
  invited_by_nickname?: string | null
  invited_by_avatar_initials?: string | null
  invited_by_background_color?: string | null
  invited_by_text_color?: string | null
  role: string
  invite_url: string
  created_at: string
  status: 'pending' | 'accepted'
  read: boolean
}

export type DepartmentInviteInboxBucket = 'unread' | 'read' | 'all'

export interface ReceivedDepartmentInvitesResponse {
  count: number
  unread_count: number
  items: ReceivedDepartmentInviteNotification[]
}

export interface CreateJoinRequestResponse {
  id: string
  status: 'pending' | 'approved' | 'rejected'
  department_id: string
  department_name: string
  assigned_role?: string | null
  auto_joined?: boolean
  created_at: string
}

export interface PendingAdminJoinRequest {
  id: string
  user_id: string
  name: string
  email?: string | null
  requested_department_name: string
  requested_affiliation?: string | null
  message?: string | null
  status?: 'pending' | 'assigned' | 'rejected'
  created_at: string
  updated_at?: string
  reviewed_by_name?: string | null
  assigned_department_id?: string | null
  assigned_department_name?: string | null
  requested_organisation_id?: string | null
  requested_parent_department_name?: string | null
}

export interface PendingDepartmentActivityInvite {
  activity_id: string
  activity_name: string
  activity_type: 'camp' | 'event' | string
  usage_start?: string | null
  usage_end?: string | null
  source_department_id: string
  source_department_name: string
  invited_at?: string | null
}

export async function createJoinRequest(options: {
  joinCode?: string
  departmentId?: string
  message?: string
  requestedRole?: string
}): Promise<CreateJoinRequestResponse> {
  const { data } = await apiClient.post<CreateJoinRequestResponse>('/api/join-requests', {
    join_code: options.joinCode,
    department_id: options.departmentId,
    message: options.message,
    requested_role: options.requestedRole
  })
  return data
}

export async function createAdminJoinRequest(payload: {
  requestedDepartmentName: string
  requestedAffiliation?: string
  requestedOrganisationId?: string
  requestedParentDepartmentName?: string
  message?: string
}): Promise<void> {
  await apiClient.post('/api/join-requests/admin-request', {
    requested_department_name: payload.requestedDepartmentName,
    requested_affiliation: payload.requestedAffiliation,
    requested_organisation_id: payload.requestedOrganisationId,
    requested_parent_department_name: payload.requestedParentDepartmentName,
    message: payload.message
  })
}

export async function getMyJoinRequests(): Promise<MyJoinRequest[]> {
  const { data } = await apiClient.get<MyJoinRequest[]>('/api/join-requests/mine')
  return data
}

export async function getPendingJoinRequests(departmentId: string): Promise<PendingJoinRequest[]> {
  const { data } = await apiClient.get<PendingJoinRequest[]>('/api/join-requests/pending', {
    params: { department_id: departmentId }
  })
  return data
}

export async function decideJoinRequest(id: string, status: 'approved' | 'rejected'): Promise<void> {
  await apiClient.patch(`/api/join-requests/${id}`, { status })
}

export async function getDepartmentInvite(departmentId: string): Promise<DepartmentInviteData> {
  const { data } = await apiClient.get<DepartmentInviteData>('/api/join-requests/invite', {
    params: { department_id: departmentId }
  })
  return data
}

export async function getPendingInvites(departmentId: string): Promise<PendingInvite[]> {
  const { data } = await apiClient.get<PendingInvite[]>('/api/join-requests/invite/pending', {
    params: { department_id: departmentId }
  })
  return data
}

export async function createPendingInvite(payload: {
  departmentId: string
  email?: string
  userId?: string
  role: string
  groupIds?: string[]
  isPrimary?: boolean
}): Promise<PendingInvite> {
  const { data } = await apiClient.post<PendingInvite>('/api/join-requests/invite/pending', {
    department_id: payload.departmentId,
    email: payload.email,
    user_id: payload.userId,
    role: payload.role,
    group_ids: payload.groupIds,
    is_primary: payload.isPrimary,
  })
  return data
}

export async function acceptDepartmentInvite(payload: {
  notificationId?: string
  departmentId?: string
  inviteId?: string
}): Promise<{ success: boolean; department_id: string; department_name: string }> {
  const { data } = await apiClient.post('/api/join-requests/invite/accept', {
    notification_id: payload.notificationId,
    department_id: payload.departmentId,
    invite_id: payload.inviteId,
  })
  return data
}

export async function declineDepartmentInvite(payload: {
  notificationId?: string
  departmentId?: string
  inviteId?: string
}): Promise<void> {
  await apiClient.post('/api/join-requests/invite/decline', {
    notification_id: payload.notificationId,
    department_id: payload.departmentId,
    invite_id: payload.inviteId,
  })
}

export async function deletePendingInvite(departmentId: string, inviteId: string): Promise<void> {
  await apiClient.delete(`/api/join-requests/invite/pending/${inviteId}`, {
    params: { department_id: departmentId }
  })
}

export async function getInviteNotifications(
  departmentId: string,
  options?: { bucket?: 'unread' | 'read' | 'all'; limit?: number },
): Promise<InviteAcceptedNotification[]> {
  const { data } = await apiClient.get<InviteAcceptedNotification[]>('/api/join-requests/invite/notifications', {
    params: {
      department_id: departmentId,
      bucket: options?.bucket,
      limit: options?.limit,
    },
  })
  return data
}

export async function markInviteNotificationRead(departmentId: string, notificationId: string): Promise<void> {
  await apiClient.patch(`/api/join-requests/invite/notifications/${notificationId}/read`, {}, {
    params: { department_id: departmentId }
  })
}

export async function getReceivedDepartmentInvites(options?: {
  bucket?: DepartmentInviteInboxBucket
  limit?: number
}): Promise<ReceivedDepartmentInvitesResponse> {
  const { data } = await apiClient.get<ReceivedDepartmentInvitesResponse>('/api/join-requests/invite/received', {
    params: {
      bucket: options?.bucket ?? 'all',
      limit: options?.limit ?? 100,
    },
  })
  return data
}

export async function markReceivedDepartmentInviteRead(notificationId: string): Promise<void> {
  await apiClient.patch(`/api/join-requests/invite/received/${notificationId}/read`, {})
}

export async function regenerateDepartmentInvite(departmentId: string): Promise<DepartmentInviteData> {
  const { data } = await apiClient.post<DepartmentInviteData>('/api/join-requests/invite/regenerate', {
    department_id: departmentId
  })
  return data
}

export async function searchJoinableDepartments(query: string): Promise<DepartmentSearchResult[]> {
  const { data } = await apiClient.get<DepartmentSearchResult[]>('/api/join-requests/departments/search', {
    params: { q: query }
  })
  return data
}

export async function getPendingAdminJoinRequests(departmentId: string): Promise<PendingAdminJoinRequest[]> {
  const { data } = await apiClient.get<PendingAdminJoinRequest[]>('/api/join-requests/admin-request/pending', {
    params: { department_id: departmentId }
  })
  return data
}

export async function decideAdminJoinRequest(
  departmentId: string,
  id: string,
  status: 'rejected'
): Promise<void> {
  await apiClient.patch(`/api/join-requests/admin-request/${id}`, { status }, {
    params: { department_id: departmentId }
  })
}

export interface AssignAdminJoinRequestResponse {
  success: boolean
  status: string
  assigned_department_id: string
  assigned_department_name: string
  assigned_role: string
  join_request_id: string
  role_forced_to_mw_warning?: string
}

export async function assignAdminJoinRequest(
  departmentId: string,
  id: string,
  targetDepartmentId: string,
  targetRole?: string
): Promise<AssignAdminJoinRequestResponse> {
  const { data } = await apiClient.patch<AssignAdminJoinRequestResponse>(
    `/api/join-requests/admin-request/${id}/assign`,
    {
      target_department_id: targetDepartmentId,
      target_role: targetRole || 'u'
    },
    { params: { department_id: departmentId } }
  )
  return data
}

export async function getAdminJoinRequestHistory(departmentId: string): Promise<PendingAdminJoinRequest[]> {
  const { data } = await apiClient.get<PendingAdminJoinRequest[]>('/api/join-requests/admin-request/history', {
    params: { department_id: departmentId }
  })
  return data
}

export async function getPendingDepartmentActivityInvites(departmentId: string): Promise<{
  count: number
  items: PendingDepartmentActivityInvite[]
}> {
  const { data } = await apiClient.get('/api/activities/department-invites/pending', {
    params: { department_id: departmentId }
  })
  return data
}

export async function decideDepartmentActivityInvite(payload: {
  activityId: string
  departmentId: string
  decision: 'accepted' | 'rejected'
}): Promise<void> {
  await apiClient.patch(`/api/activities/${payload.activityId}/department-invites/decision`, {
    department_id: payload.departmentId,
    decision: payload.decision,
  })
}
