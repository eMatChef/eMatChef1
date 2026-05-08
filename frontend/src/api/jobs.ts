import apiClient from './apiClient'

export interface UnassignedCleanupItem {
  user_id: string
  email: string | null
  created_at: string
}

export interface UnassignedCleanupPreviewResponse {
  days: number
  count: number
  items: UnassignedCleanupItem[]
}

export interface UnassignedCleanupRunResponse {
  dry_run: boolean
  days: number
  count?: number
  items?: UnassignedCleanupItem[]
  requested_users?: number | null
  deleted_users?: number
  deleted_profiles?: number
}

export async function previewUnassignedUsersCleanup(days = 21): Promise<UnassignedCleanupPreviewResponse> {
  const { data } = await apiClient.get<UnassignedCleanupPreviewResponse>('/api/jobs/unassigned-users-cleanup/preview', {
    params: { days }
  })
  return data
}

export async function runUnassignedUsersCleanup(
  days = 21,
  dryRun = false,
  userIds: string[] = []
): Promise<UnassignedCleanupRunResponse> {
  const { data } = await apiClient.post<UnassignedCleanupRunResponse>('/api/jobs/unassigned-users-cleanup/run', {
    days,
    dry_run: dryRun,
    user_ids: userIds
  })
  return data
}
